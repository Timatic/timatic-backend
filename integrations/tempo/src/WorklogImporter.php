<?php

declare(strict_types=1);

namespace Timatic\Tempo;

use App\DataTransferObjects\Ticket;
use App\Integrations\Tickets\TicketProviderRegistry;
use App\Models\Entry;
use App\Models\Integration;
use App\Services\ClosedMonthDateService;
use App\Services\EntryEnricher;
use App\Services\EntryLockedDateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Timatic\Jira\JiraDirectory;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;

/**
 * Copies Tempo worklogs to entries, with the issue, author and project mapping of the Jira instance Tempo runs on.
 * Entries are matched on the worklog id, so importing the same worklog twice is harmless. Entries that are invoiced
 * or in a locked period are left untouched and logged.
 *
 * @arch-ignore app-boundary: turns worklogs into entries with the enricher and lock rules of the core
 */
final class WorklogImporter
{
    public function __construct(
        private readonly JiraDirectory $jiraDirectory,
        private readonly EntryEnricher $entryEnricher,
        private readonly TicketProviderRegistry $ticketProviders,
    ) {}

    public static function isReady(Integration $tempo): bool
    {
        return filled($tempo->config['api_token'] ?? null)
            && app(JiraDirectory::class)->isConnected(Integration::find((int) ($tempo->config['jira_integration_id'] ?? 0)));
    }

    /** @param Collection<int, TempoWorklog> $worklogs */
    public function import(Integration $tempo, Collection $worklogs): void
    {
        $jira = Integration::findOrFail((int) $tempo->config['jira_integration_id']);
        $tickets = $this->ticketProviders->makeProvider($jira->type, [...$jira->config, 'integration_id' => $jira->id]);
        // Tempo only knows the numeric issue id, which Jira accepts wherever it accepts an issue key
        $issues = $worklogs->pluck('issueId')->unique()
            ->mapWithKeys(fn (string $issueId): array => [$issueId => $tickets->fetchTicketByKey($issueId)])
            ->filter()
            ->map(fn (Ticket $ticket) => $this->resolveMirroredIssue($ticket, $tempo));
        $users = $this->jiraDirectory->timaticUsers($jira, $worklogs->pluck('authorAccountId'));

        // Lets the outbound subscriber tell an imported change from one made in Timatic
        Context::add('entry_sync_source', true);

        try {
            foreach ($worklogs as $worklog) {
                $issue = $issues->get($worklog->issueId);
                $mapping = $issue === null ? null : $this->findBudgetMapping($issue, $jira);
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
        } finally {
            Context::forget('entry_sync_source');
        }
    }

    /** @param Collection<int, string> $worklogIds */
    public function remove(Collection $worklogIds): void
    {
        Context::add('entry_sync_source', true);

        try {
            foreach (Entry::query()->whereIn('external_id', $worklogIds)->get() as $entry) {
                if ($this->isLocked($entry)) {
                    Log::warning("Tempo worklog {$entry->external_id} deleted but entry {$entry->id} is locked or invoiced");

                    continue;
                }

                $entry->delete();
            }
        } finally {
            Context::forget('entry_sync_source');
        }
    }

    /** The mapping of the issue's project, preferring the linked Jira instance when the key exists in several instances */
    private function findBudgetMapping(Ticket $issue, Integration $jira): ?ProjectMapping
    {
        return ProjectMapping::active()
            ->where('project_key', strtoupper(explode('-', $issue->number)[0]))
            ->whereNotNull('customer_id')
            ->whereNotNull('budget_id')
            ->orderByRaw('integration_id = ? desc', [$jira->id])
            ->first();
    }

    /**
     * Issues mirrored from another Jira instance start with the original issue key, e.g. "ABC-12 Fix login".
     * Time on such a copy counts for the original issue.
     */
    private function resolveMirroredIssue(Ticket $issue, Integration $tempo): Ticket
    {
        $mirroredIssuePrefixes = ($tempo->config['mirrored_issues_enabled'] ?? false) ? ($tempo->config['mirrored_issue_prefixes'] ?? []) : [];

        if ($mirroredIssuePrefixes === []) {
            return $issue;
        }

        $prefixes = implode('|', array_map(fn (string $prefix) => preg_quote($prefix, '/'), $mirroredIssuePrefixes));

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
