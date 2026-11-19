<?php

namespace Timatic\Jira;

use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use App\Services\ClosedMonthDateService;
use App\Services\EntryEnricher;
use App\Services\EntryLockedDateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\DataTransferObjects\JiraWorklog;
use Timatic\Jira\DataTransferObjects\WorklogSyncReport;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserByAccountIdRequest;

// @arch-ignore app-boundary: turns worklogs into entries with the enricher and lock rules of the core
final class WorklogSync
{
    private Connector $connector;

    private int $integrationId;

    private int $created = 0;

    private int $updated = 0;

    private int $deleted = 0;

    private int $skipped = 0;

    /** @var array<int, string> */
    private array $mirroredIssuePrefixes = [];

    public function __construct(
        private readonly OAuthService $oauthService,
        private readonly EntryEnricher $entryEnricher,
    ) {}

    /**
     * Copies the Jira worklogs changed since the given moment to entries and removes the entries of deleted worklogs.
     * Entries that are invoiced or in a locked period are left untouched and logged.
     */
    public function run(Integration $integration, int $updatedSinceMilliseconds, int $deletedSinceMilliseconds, WorklogFeed $feed): WorklogSyncReport
    {
        $integration = $this->oauthService->refreshIfExpired($integration);
        $this->connector = new Connector($integration->config);
        $this->integrationId = $integration->id;
        // Lets outbound listeners tell an imported change from one made in Timatic
        Context::add('entry_sync_source', true);
        $this->mirroredIssuePrefixes = ($integration->config['mirrored_issues_enabled'] ?? false)
            ? ($integration->config['mirrored_issue_prefixes'] ?? [])
            : [];
        $this->created = $this->updated = $this->deleted = $this->skipped = 0;

        $changes = $feed->changedSince($updatedSinceMilliseconds);
        $deleted = $feed->deletedSince($deletedSinceMilliseconds);

        try {
            $this->syncUpdated($changes->worklogs);
            $this->syncDeleted($deleted->worklogIds);
        } finally {
            Context::forget('entry_sync_source');
        }

        return new WorklogSyncReport($this->created, $this->updated, $this->deleted, $this->skipped, $changes->nextSinceMilliseconds, $deleted->nextSinceMilliseconds);
    }

    /** @param Collection<int, JiraWorklog> $worklogs */
    private function syncUpdated(Collection $worklogs): void
    {
        $issues = $this->fetchIssues($worklogs->pluck('issueId')->unique())->map(fn (JiraIssue $issue) => $this->resolveMirroredIssue($issue));
        $users = $this->fetchUsers($worklogs->pluck('authorAccountId')->unique());

        foreach ($worklogs as $worklog) {
            $issue = $issues->get($worklog->issueId);
            $mapping = $issue === null ? null : $this->findBudgetMapping($issue);
            $user = $users->get($worklog->authorAccountId);

            if ($issue === null || $mapping === null || $user === null) {
                Log::warning("Jira worklog {$worklog->id} skipped: no issue, budget mapping or Timatic user", ['issue' => $issue?->key, 'author' => $worklog->authorAccountId]);
                $this->skipped++;

                continue;
            }

            $entry = Entry::withTrashed()->firstWhere('external_id', $worklog->id);

            if ($entry?->trashed()) {
                $this->skipped++;

                continue;
            }

            if ($entry !== null && $this->isLocked($entry)) {
                Log::warning("Jira worklog {$worklog->id} changed but entry {$entry->id} is locked or invoiced");
                $this->skipped++;

                continue;
            }

            $isNew = $entry === null;
            $entry ??= new Entry(['external_id' => $worklog->id]);
            $entry->fill([
                'ticket_id' => $issue->key,
                'ticket_number' => $issue->key,
                'ticket_title' => $issue->summary,
                'ticket_type' => $issue->issueType,
                'customer_id' => $mapping->customer_id,
                'budget_id' => $mapping->budget_id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_full_name' => $user->full_name,
                'entry_type' => 'regular',
                'description' => $worklog->comment !== '' ? $worklog->comment : null,
                'is_internal' => false,
                'started_at' => $worklog->startedAt,
                'ended_at' => $worklog->startedAt->addSeconds($worklog->timeSpentSeconds),
            ]);

            // Jira's since filter is inclusive, so the last changed worklog comes back every run
            if (! $entry->isDirty()) {
                continue;
            }

            $entry->save();
            $this->entryEnricher->enrich($entry, isPartialUpdate: false, hasOvertime: false, hasCustomerOvertime: false, overtimeStartedAt: null, overtimeEndedAt: null);

            if ($isNew) {
                $this->created++;
            } else {
                $this->updated++;
            }
        }
    }

    /** @param Collection<int, string> $worklogIds */
    private function syncDeleted(Collection $worklogIds): void
    {
        Entry::query()
            ->whereIn('external_id', $worklogIds)
            ->get()
            ->each(function (Entry $entry): void {
                if ($this->isLocked($entry)) {
                    Log::warning("Jira worklog {$entry->external_id} deleted but entry {$entry->id} is locked or invoiced");
                    $this->skipped++;

                    return;
                }

                $entry->delete();
                $this->deleted++;
            });
    }

    private function findBudgetMapping(JiraIssue $issue): ?ProjectMapping
    {
        return ProjectMapping::active()
            ->where('project_key', strtoupper(explode('-', $issue->key)[0]))
            ->whereNotNull('customer_id')
            ->whereNotNull('budget_id')
            ->orderByRaw('integration_id = ? desc', [$this->integrationId])
            ->first();
    }

    /**
     * Issues mirrored from another Jira instance start with the original issue key, e.g. "SILO-12 Fix login".
     * Time on such a copy counts for the original issue.
     */
    private function resolveMirroredIssue(JiraIssue $issue): JiraIssue
    {
        if ($this->mirroredIssuePrefixes === []) {
            return $issue;
        }

        $prefixes = implode('|', array_map(fn (string $prefix) => preg_quote($prefix, '/'), $this->mirroredIssuePrefixes));

        if (preg_match('/^((?:'.$prefixes.')-\d+)\b[\s:\-]*(.*)$/i', $issue->summary, $matches) !== 1) {
            return $issue;
        }

        $title = trim($matches[2]);

        return new JiraIssue(
            key: strtoupper($matches[1]),
            issueType: $issue->issueType,
            summary: $title !== '' ? $title : $issue->summary,
            createdAt: $issue->createdAt,
            resolvedAt: $issue->resolvedAt,
            id: $issue->id,
        );
    }

    private function isLocked(Entry $entry): bool
    {
        if ($entry->is_locked || $entry->is_invoiced || $entry->user === null) {
            return true;
        }

        return (new EntryLockedDateService($entry->user, new ClosedMonthDateService($entry->user)))->get()->isAfter($entry->started_at);
    }

    /**
     * @param  Collection<int, string>  $issueIds
     * @return Collection<string, JiraIssue> keyed by issue id
     */
    private function fetchIssues(Collection $issueIds): Collection
    {
        return $issueIds->chunk(50)
            ->flatMap(function (Collection $chunk): array {
                /** @var array<int, JiraIssue> $issues */
                $issues = $this->connector->send(new GetIssuesRequest('id in ('.$chunk->implode(', ').')'))->dto();

                return $issues;
            })
            ->keyBy(fn (JiraIssue $issue) => (string) $issue->id);
    }

    /**
     * @param  Collection<int, string>  $accountIds
     * @return Collection<string, User> keyed by Jira account id
     */
    private function fetchUsers(Collection $accountIds): Collection
    {
        $emails = $accountIds->mapWithKeys(function (string $accountId): array {
            $email = Cache::get('jira.email.'.$accountId) ?? $this->connector->send(new GetUserByAccountIdRequest($accountId))->dto()->emailAddress;

            if ($email !== '') {
                Cache::forever('jira.email.'.$accountId, $email);
                Cache::forever('jira.account_id.'.strtolower($email), $accountId);
            }

            return [$accountId => strtolower($email)];
        })->filter();

        $users = User::query()->whereIn('email', $emails->values())->get()->keyBy(fn (User $user) => strtolower($user->email));

        return $emails->map(fn (string $email) => $users->get($email))->filter();
    }
}
