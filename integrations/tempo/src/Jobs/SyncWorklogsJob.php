<?php

declare(strict_types=1);

namespace Timatic\Tempo\Jobs;

use App\DataTransferObjects\Ticket;
use App\Integrations\Tickets\TicketProviderRegistry;
use App\Models\Entry;
use App\Models\Integration;
use App\Services\ClosedMonthDateService;
use App\Services\EntryEnricher;
use App\Services\EntryLockedDateService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Saloon\Http\Response;
use Throwable;
use Timatic\Jira\JiraDirectory;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklog;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;
use Timatic\Tempo\DataTransferObjects\WorklogSyncRun;
use Timatic\Tempo\Exceptions\WorklogSyncNotStartedException;
use Timatic\Tempo\Connector;
use Timatic\Tempo\Requests\GetChangedWorklogsRequest;
use Timatic\Tempo\WorklogSyncTracker;
use Timatic\Tempo\Requests\GetDeletedWorklogsRequest;

/**
 * Syncs one page of Tempo worklogs and queues the next page, so a long backfill never outlives the worker timeout.
 *
 * @arch-ignore app-boundary: turns worklogs into entries with the enricher and lock rules of the core
 */
class SyncWorklogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    private JiraDirectory $jiraDirectory;

    private EntryEnricher $entryEnricher;

    private WorklogSyncTracker $tracker;

    private Integration $jira;

    /** @var array<int, string> */
    private array $mirroredIssuePrefixes = [];

    public function __construct(public readonly WorklogSyncRun $run) {}

    public static function isReady(Integration $tempo): bool
    {
        return filled($tempo->config['api_token'] ?? null)
            && app(JiraDirectory::class)->isConnected(Integration::find((int) ($tempo->config['jira_integration_id'] ?? 0)));
    }

    /**
     * Queues a sync of the Tempo worklogs changed since the given moment, or since the stored position when none is given.
     *
     * @throws WorklogSyncNotStartedException
     */
    public static function start(Integration $tempo, ?CarbonImmutable $since = null): void
    {
        if (! self::isReady($tempo)) {
            throw WorklogSyncNotStartedException::notConnected();
        }

        $now = now()->getTimestampMs();
        $requestedSince = $since?->getTimestampMs();
        $updatedSince = (int) ($requestedSince ?? $tempo->config['worklog_sync_updated_since'] ?? $now);

        $run = new WorklogSyncRun(
            id: (string) Str::uuid(),
            integrationId: $tempo->id,
            updatedSinceMilliseconds: $updatedSince,
            deletedSinceMilliseconds: (int) ($requestedSince ?? $tempo->config['worklog_sync_deleted_since'] ?? $now),
            startedAtMilliseconds: $now,
            latestUpdatedMilliseconds: $updatedSince,
        );

        if (! app(WorklogSyncTracker::class)->claim($run)) {
            throw WorklogSyncNotStartedException::alreadyRunning();
        }

        self::dispatch($run);
    }

    /**
     * Copies one page of changed Tempo worklogs to entries; after the last page the entries of deleted worklogs are removed
     * and the sync position is stored. Issues, authors and the project mapping are resolved through the Jira instance Tempo runs on.
     * Entries that are invoiced or in a locked period are left untouched and logged.
     */
    public function handle(JiraDirectory $jiraDirectory, EntryEnricher $entryEnricher, WorklogSyncTracker $tracker): void
    {
        $this->jiraDirectory = $jiraDirectory;
        $this->entryEnricher = $entryEnricher;
        $this->tracker = $tracker;
        $run = $this->run;

        // The claim of this run expired while it waited in the queue and another run took its place
        if ($tracker->active($run->integrationId)?->id !== $run->id && ! $tracker->claim($run)) {
            return;
        }

        $tempo = Integration::findOrFail($run->integrationId);
        $this->jira = Integration::findOrFail((int) $tempo->config['jira_integration_id']);
        $this->mirroredIssuePrefixes = ($tempo->config['mirrored_issues_enabled'] ?? false)
            ? ($tempo->config['mirrored_issue_prefixes'] ?? [])
            : [];

        // Tempo keeps the real author and description that it strips from its Jira copies
        $connector = new Connector($tempo->config['api_token']);
        $pages = (new GetChangedWorklogsRequest(CarbonImmutable::createFromTimestampMs($run->updatedSinceMilliseconds)))
            ->paginate($connector)
            ->setStartPage($run->page);
        $pages->rewind();
        /** @var Response $response */
        $response = $pages->current();
        /** @var Collection<int, TempoWorklog> $worklogs */
        $worklogs = $response->dto();
        $run = $run->with(
            latestUpdatedMilliseconds: max($run->latestUpdatedMilliseconds, $worklogs->max(fn (TempoWorklog $worklog) => $worklog->updatedAt->getTimestampMs()) ?? 0),
        );

        // Lets the outbound subscriber tell an imported change from one made in Timatic
        Context::add('entry_sync_source', true);

        try {
            $this->syncUpdated($worklogs);

            // Each page runs in its own job; valid() tells whether Tempo has a page after the current one
            if ($pages->valid()) {
                // ponytail: a worklog deleted in Tempo mid-run shifts the next page by one and skips a worklog; sync again from the same date if a backfill misses one
                $run = $run->with(page: $run->page + 1);
                $this->tracker->update($run);
                self::dispatch($run);

                return;
            }

            /** @var Collection<int, TempoDeletedWorklog> $deleted */
            $deleted = (new GetDeletedWorklogsRequest(CarbonImmutable::createFromTimestampMs($run->deletedSinceMilliseconds)))
                ->paginate($connector)
                ->collect()
                ->collect();
            $this->syncDeleted($deleted->pluck('id')->unique()->values());
        } finally {
            Context::forget('entry_sync_source');
        }

        // Read again: the settings may have been saved while the run was busy
        $config = $tempo->refresh()->config ?? [];
        $tempo->update(['config' => array_merge($config, [
            // Worklogs changed while the run was busy may have been paged past, so never move beyond its start
            'worklog_sync_updated_since' => min($run->latestUpdatedMilliseconds, $run->startedAtMilliseconds),
            'worklog_sync_deleted_since' => max($run->deletedSinceMilliseconds, $deleted->max(fn (TempoDeletedWorklog $worklog) => $worklog->deletedAt->getTimestampMs()) ?? 0),
            'worklog_sync_from' => min($config['worklog_sync_from'] ?? PHP_INT_MAX, $run->updatedSinceMilliseconds),
            'worklog_synced_until' => $run->startedAtMilliseconds,
        ])]);

        $this->tracker->close($run);
    }

    public function failed(?Throwable $exception): void
    {
        app(WorklogSyncTracker::class)->close($this->run);
    }

    /** @param Collection<int, TempoWorklog> $worklogs */
    private function syncUpdated(Collection $worklogs): void
    {
        $tickets = app(TicketProviderRegistry::class)->makeProvider($this->jira->type, [...$this->jira->config, 'integration_id' => $this->jira->id]);
        // Tempo only knows the numeric issue id, which Jira accepts wherever it accepts an issue key
        $issues = $worklogs->pluck('issueId')->unique()
            ->mapWithKeys(fn (string $issueId): array => [$issueId => $tickets->fetchTicketByKey($issueId)])
            ->filter()
            ->map(fn (Ticket $ticket) => $this->resolveMirroredIssue($ticket));
        $users = $this->jiraDirectory->timaticUsers($this->jira, $worklogs->pluck('authorAccountId'));

        foreach ($worklogs as $worklog) {
            $issue = $issues->get($worklog->issueId);
            $mapping = $issue === null ? null : $this->findBudgetMapping($issue);
            $user = $users->get($worklog->authorAccountId);

            if ($issue === null || $mapping === null || $user === null) {
                Log::warning("Tempo worklog {$worklog->id} skipped: no issue, budget mapping or Timatic user", ['issue' => $issue?->number, 'author' => $worklog->authorAccountId]);

                continue;
            }

            $entry = Entry::withTrashed()->firstWhere('external_id', $worklog->id);

            if ($entry?->trashed()) {
                continue;
            }

            if ($entry !== null && $this->isLocked($entry)) {
                Log::warning("Tempo worklog {$worklog->id} changed but entry {$entry->id} is locked or invoiced");

                continue;
            }

            $entry ??= new Entry(['external_id' => $worklog->id]);
            $entry->fill([
                'ticket_id' => $issue->number,
                'ticket_number' => $issue->number,
                'ticket_title' => $issue->title,
                'ticket_type' => $issue->type,
                'customer_id' => $mapping->customer_id,
                'budget_id' => $mapping->budget_id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_full_name' => $user->full_name,
                'entry_type' => 'regular',
                'description' => $worklog->description !== '' ? $worklog->description : null,
                'is_internal' => false,
                'started_at' => $worklog->startedAt,
                'ended_at' => $worklog->startedAt->addSeconds($worklog->timeSpentSeconds),
            ]);

            // Tempo's updatedFrom filter is inclusive, so the last changed worklog comes back every run
            if (! $entry->isDirty()) {
                continue;
            }

            $entry->save();
            $this->entryEnricher->enrich($entry, isPartialUpdate: false, hasOvertime: false, hasCustomerOvertime: false, overtimeStartedAt: null, overtimeEndedAt: null);
        }
    }

    /** @param Collection<int, string> $worklogIds */
    private function syncDeleted(Collection $worklogIds): void
    {
        foreach (Entry::query()->whereIn('external_id', $worklogIds)->get() as $entry) {
            if ($this->isLocked($entry)) {
                Log::warning("Tempo worklog {$entry->external_id} deleted but entry {$entry->id} is locked or invoiced");

                continue;
            }

            $entry->delete();
        }
    }

    /** The mapping of the issue's project, preferring the linked Jira instance when the key exists in several instances */
    private function findBudgetMapping(Ticket $issue): ?ProjectMapping
    {
        return ProjectMapping::active()
            ->where('project_key', strtoupper(explode('-', $issue->number)[0]))
            ->whereNotNull('customer_id')
            ->whereNotNull('budget_id')
            ->orderByRaw('integration_id = ? desc', [$this->jira->id])
            ->first();
    }

    /**
     * Issues mirrored from another Jira instance start with the original issue key, e.g. "ABC-12 Fix login".
     * Time on such a copy counts for the original issue.
     */
    private function resolveMirroredIssue(Ticket $issue): Ticket
    {
        if ($this->mirroredIssuePrefixes === []) {
            return $issue;
        }

        $prefixes = implode('|', array_map(fn (string $prefix) => preg_quote($prefix, '/'), $this->mirroredIssuePrefixes));

        if (preg_match('/^((?:'.$prefixes.')-\d+)\b[\s:\-]*(.*)$/i', $issue->title, $matches) !== 1) {
            return $issue;
        }

        $key = strtoupper($matches[1]);
        $title = trim($matches[2]);

        return new Ticket(
            type: $issue->type,
            id: $key,
            number: $key,
            title: $title !== '' ? $title : $issue->title,
            created_at: $issue->created_at,
            closed_at: $issue->closed_at,
            url: $issue->url,
        );
    }

    private function isLocked(Entry $entry): bool
    {
        if ($entry->is_locked || $entry->is_invoiced || $entry->user === null) {
            return true;
        }

        return (new EntryLockedDateService($entry->user, new ClosedMonthDateService($entry->user)))->get()->isAfter($entry->started_at);
    }
}
