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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Timatic\Jira\JiraDirectory;
use Timatic\Jira\Models\EpicMapping;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Models\UserMapping;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;

/**
 * Copies Tempo worklogs to entries, with the issue, author and project mapping of the Jira instance Tempo runs on.
 * In a project mapped per epic the mapping of the issue's epic comes first. Entries are matched on the worklog id,
 * so importing the same worklog twice is harmless. Entries that are invoiced or in a locked period are left
 * untouched and logged. A worklog without a mapped author, epic or project is skipped, and the Tempo integration
 * remembers that Jira account, epic or project so its settings can link to exactly those.
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
        $jiraIssues = $worklogs->pluck('issueId')->unique()
            ->mapWithKeys(fn (string $issueId): array => [$issueId => $tickets->fetchTicketByKey($issueId)])
            ->filter();
        $issues = $jiraIssues->map(fn (Ticket $ticket) => $this->resolveMirroredIssue($ticket, $tempo));
        $epicKeys = $this->findEpicKeys($jira, $jiraIssues, $issues);
        $users = $this->jiraDirectory->timaticUsers($jira, $worklogs->pluck('authorAccountId'));
        $unmappedAccountIds = [];
        $unmappedEpicKeys = [];
        $unmappedProjectKeys = [];

        // Lets the outbound subscriber tell an imported change from one made in Timatic
        Context::add('entry_sync_source', true);

        try {
            foreach ($worklogs as $worklog) {
                $issue = $issues->get($worklog->issueId);
                $epicKey = $issue === null ? null : $epicKeys->get($issue->number);
                $mapping = $issue === null ? null : $this->findBudgetMapping($issue, $epicKey, $jira);
                $user = $users->get($worklog->authorAccountId);

                if ($issue === null || $mapping === null || $user === null) {
                    Log::warning("Tempo worklog {$worklog->id} skipped: no issue, budget mapping or Timatic user", ['issue' => $issue?->number, 'author' => $worklog->authorAccountId]);

                    if ($user === null) {
                        $unmappedAccountIds[] = $worklog->authorAccountId;
                    }

                    if ($epicKey !== null && $mapping === null) {
                        $unmappedEpicKeys[] = $epicKey;
                    } elseif ($issue !== null && $mapping === null) {
                        $unmappedProjectKeys[] = $this->projectKey($issue);
                    }

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

            $this->rememberSkipped($tempo, $jira, $unmappedAccountIds, $unmappedEpicKeys, $unmappedProjectKeys, $jiraIssues->map($this->projectKey(...))->all());
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

    /**
     * The epic of every issue in a project that is mapped per epic. A mirrored issue is left out:
     * its time counts for the original issue, which the linked Jira instance does not have.
     *
     * @param  Collection<array-key, Ticket>  $jiraIssues
     * @param  Collection<array-key, Ticket>  $issues
     * @return Collection<string, non-falsy-string> keyed by issue key
     */
    private function findEpicKeys(Integration $jira, Collection $jiraIssues, Collection $issues): Collection
    {
        $projectKeys = ProjectMapping::active()->where('integration_id', $jira->id)->where('is_mapped_per_epic', true)->pluck('project_key');

        return $this->jiraDirectory->epicKeys($jira, $jiraIssues
            ->filter(fn (Ticket $jiraIssue, int|string $issueId): bool => $issues->get($issueId) === $jiraIssue && $projectKeys->contains($this->projectKey($jiraIssue)))
            ->pluck('number'));
    }

    /**
     * The mapping of the issue's epic, otherwise the mapping of its project,
     * preferring the linked Jira instance when the project key exists in several instances.
     */
    private function findBudgetMapping(Ticket $issue, ?string $epicKey, Integration $jira): EpicMapping|ProjectMapping|null
    {
        $epicMapping = $epicKey === null ? null : EpicMapping::query()
            ->where('integration_id', $jira->id)
            ->where('epic_key', $epicKey)
            ->whereNotNull('customer_id')
            ->whereNotNull('budget_id')
            ->first();

        return $epicMapping ?? ProjectMapping::active()
            ->where('project_key', $this->projectKey($issue))
            ->whereNotNull('customer_id')
            ->whereNotNull('budget_id')
            ->orderByRaw('integration_id = ? desc', [$jira->id])
            ->first();
    }

    private function projectKey(Ticket $issue): string
    {
        return strtoupper(explode('-', $issue->number)[0]);
    }

    /**
     * @param  array<int, string>  $accountIds
     * @param  array<int, string>  $epicKeys
     * @param  array<int, string>  $projectKeys
     * @param  array<int|string, string>  $linkedJiraProjectKeys
     */
    private function rememberSkipped(Integration $tempo, Integration $jira, array $accountIds, array $epicKeys, array $projectKeys, array $linkedJiraProjectKeys): void
    {
        // An epic nobody loaded on the mapping page yet has no row to map
        foreach (array_unique($epicKeys) as $epicKey) {
            EpicMapping::query()->firstOrCreate(['integration_id' => $jira->id, 'epic_key' => $epicKey], ['epic_name' => $epicKey]);
        }

        // A project nobody loaded on the mapping page yet has no row to map. The project of a mirrored original
        // does not exist in the linked Jira instance, where a row for it would break the ticket search by project.
        foreach (array_unique(array_intersect($projectKeys, $linkedJiraProjectKeys)) as $projectKey) {
            ProjectMapping::query()->firstOrCreate(['integration_id' => $jira->id, 'project_key' => $projectKey], ['project_name' => $projectKey]);
        }

        $userMappingIds = UserMapping::query()->where('integration_id', $jira->id)->whereIn('account_id', $accountIds)->pluck('id')->all();
        $epicMappingIds = EpicMapping::query()->where('integration_id', $jira->id)->whereIn('epic_key', $epicKeys)->pluck('id')->all();
        $projectMappingIds = ProjectMapping::active()->whereIn('project_key', $projectKeys)->pluck('id')->all();

        if ($userMappingIds === [] && $epicMappingIds === [] && $projectMappingIds === []) {
            return;
        }

        // ponytail: the lock only keeps concurrent imports apart; a sync or settings save writing at the same moment
        // can still drop these ids, which return with the next skipped worklog. Own table if that ever hurts.
        DB::transaction(function () use ($tempo, $userMappingIds, $epicMappingIds, $projectMappingIds): void {
            $config = Integration::query()->lockForUpdate()->findOrFail($tempo->id)->config ?? [];

            $tempo->update(['config' => array_merge($config, [
                'worklog_skipped_user_mapping_ids' => array_values(array_unique([...$config['worklog_skipped_user_mapping_ids'] ?? [], ...$userMappingIds])),
                'worklog_skipped_epic_mapping_ids' => array_values(array_unique([...$config['worklog_skipped_epic_mapping_ids'] ?? [], ...$epicMappingIds])),
                'worklog_skipped_project_mapping_ids' => array_values(array_unique([...$config['worklog_skipped_project_mapping_ids'] ?? [], ...$projectMappingIds])),
            ])]);
        });
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
