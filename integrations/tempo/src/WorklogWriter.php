<?php

namespace Timatic\Tempo;

use App\Models\Entry;
use App\Models\Integration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Timatic\Jira\JiraDirectory;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPayload;
use Timatic\Tempo\DataTransferObjects\TempoWorklogReference;
use Timatic\Tempo\Requests\CreateWorklogRequest;
use Timatic\Tempo\Requests\DeleteWorklogRequest;
use Timatic\Tempo\Requests\UpdateWorklogRequest;

/**
 * Mirrors an entry to Tempo as a worklog of the entry's user, for every Tempo
 * integration whose linked Jira instance maps the entry's project.
 */
final class WorklogWriter
{
    public function __construct(private readonly JiraDirectory $jiraDirectory) {}

    public function push(Entry $entry): void
    {
        foreach ($this->integrationsFor($entry) as $tempo) {
            $jira = Integration::find((int) ($tempo->config['jira_integration_id'] ?? 0));
            $issueId = $this->jiraDirectory->isConnected($jira) ? $this->jiraDirectory->findIssueId($jira, $entry->ticket_number) : null;
            $accountId = $jira !== null && $issueId !== null && $entry->user !== null ? $this->jiraDirectory->findAccountId($jira, $entry->user->email) : null;

            if ($issueId === null || $accountId === null) {
                Log::warning("Entry {$entry->id} not pushed to Tempo: Jira issue or account of the user not found", ['ticket' => $entry->ticket_number, 'user' => $entry->user?->email]);

                continue;
            }

            $connector = new Connector($tempo->config['api_token']);
            $payload = TempoWorklogPayload::fromEntry($entry, $issueId, $accountId);
            if ($entry->external_id !== null) {
                $connector->send(new UpdateWorklogRequest($entry->external_id, $payload))->throw();

                continue;
            }

            /** @var TempoWorklogReference $created */
            $created = $connector->send(new CreateWorklogRequest($payload))->throw()->dto();
            $entry->forceFill(['external_id' => $created->tempoWorklogId])->saveQuietly();
        }
    }

    public function remove(Entry $entry): void
    {
        foreach ($this->integrationsFor($entry) as $tempo) {
            if ($entry->external_id === null) {
                continue;
            }

            $response = new Connector($tempo->config['api_token'])->send(new DeleteWorklogRequest($entry->external_id));

            if ($response->status() !== 404) {
                $response->throw();
            }
        }
    }

    /** @return Collection<int, Integration> */
    private function integrationsFor(Entry $entry): Collection
    {
        $projectKey = strtoupper((string) strtok((string) $entry->ticket_number, '-'));

        if ($projectKey === '') {
            return collect();
        }

        return Integration::query()->where('type', 'tempo')->get()
            ->filter(fn (Integration $tempo): bool => ($tempo->config['sync_to_tempo'] ?? false)
                && filled($tempo->config['api_token'] ?? null)
                && ProjectMapping::active()->where('integration_id', (int) ($tempo->config['jira_integration_id'] ?? 0))->where('project_key', $projectKey)->exists());
    }
}
