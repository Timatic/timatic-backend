<?php

namespace Timatic\Jira;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Jira\DataTransferObjects\JiraChangelogEntry;
use Timatic\Jira\DataTransferObjects\JiraComment;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\DataTransferObjects\JiraUser;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssueRequest;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserRequest;

final class TicketProvider implements TicketProviderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        return new self($config);
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection
    {
        if ($search === null || $search === '') {
            if ($customer !== null) {
                return $this->fetchEpicsByCustomer($customer);
            }

            return $this->fetchTicketsByUserHistory($user);
        }

        $jql = preg_match('/^[A-Z]{2,3}-\d+$/i', $search)
            ? 'key = "'.$this->escapeJqlString($search).'"'
            : 'text ~ "'.$this->escapeJqlString($search).'*"';

        $projectKey = $this->resolveProjectKey($customer);

        if ($projectKey !== null) {
            $jql .= ' AND project = "'.$this->escapeJqlString($projectKey).'"';
        }

        return $this->fetchIssuesByJql($jql);
    }

    private function resolveProjectKey(?Customer $customer): ?string
    {
        if ($customer === null || ! isset($this->config['integration_id'])) {
            return null;
        }

        return ProjectMapping::where('integration_id', $this->config['integration_id'])
            ->where('customer_id', $customer->id)
            ->active()
            ->value('project_key');
    }

    /** @return Collection<int, Ticket> */
    private function fetchTicketsByUserHistory(?User $user): Collection
    {
        if ($user === null || $user->email === null) {
            return collect();
        }

        $accountId = $this->resolveJiraAccountId($user->email);

        if ($accountId === null) {
            return collect();
        }

        return $this->fetchIssuesByJql('reporter = "'.$this->escapeJqlString($accountId).'" ORDER BY created DESC');
    }

    /** @return Collection<int, Ticket> */
    private function fetchEpicsByCustomer(Customer $customer): Collection
    {
        $projectKey = $this->resolveProjectKey($customer);

        if ($projectKey === null) {
            return collect();
        }

        $jql = 'project = "'.$this->escapeJqlString($projectKey).'" AND issuetype = Epic AND statusCategory != Done ORDER BY created DESC';

        return $this->fetchIssuesByJql($jql);
    }

    public static function ticketKeyPattern(): string
    {
        $keys = ProjectMapping::active()
            ->pluck('project_key')
            ->map(fn ($key) => preg_quote($key, '/'))
            ->implode('|');

        return ($keys !== '' ? '(?:'.$keys.')' : '[A-Z]+').'-\d+';
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        $ticket = $this->fetchIssuesByJql('key = "'.$this->escapeJqlString(strtoupper($key)).'"')->first();

        if ($ticket === null) {
            return null;
        }

        $projectKey = strtoupper(explode('-', $ticket->number)[0]);
        $mapping = ProjectMapping::where('project_key', $projectKey)->active()->first();

        return $ticket->withMapping($mapping?->customer_id, $mapping?->budget_id);
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        $response = $this->connector()->send(new GetIssueRequest(strtoupper($key)));

        if ($response->status() === 404) {
            return null;
        }

        $data = $response->json();

        $projectKey = strtoupper(explode('-', $key)[0]);
        $mapping = ProjectMapping::where('project_key', $projectKey)->active()->first();

        $actions = $this->mergeActions(
            $this->mapComments($data['renderedFields']['comment']['comments'] ?? [], $data['fields']['comment']['comments'] ?? []),
            $this->mapChangelog($data['changelog']['histories'] ?? []),
        );

        $details = new Ticket(
            type: $data['fields']['issuetype']['name'] ?? '',
            id: $data['key'],
            number: $data['key'],
            title: $data['fields']['summary'] ?? '',
            created_at: CarbonImmutable::parse($data['fields']['created']),
            closed_at: isset($data['fields']['resolutiondate']) ? CarbonImmutable::parse($data['fields']['resolutiondate']) : null,
            url: ($this->config['cloud_url'] ?? '').'/browse/'.$data['key'],
            actions: $actions,
        );

        return $details->withMapping($mapping?->customer_id, $mapping?->budget_id);
    }

    /** @return Collection<int, Ticket> */
    private function fetchIssuesByJql(string $jql): Collection
    {
        /** @var array<int, JiraIssue> $issues */
        $issues = $this->connector()->send(new GetIssuesRequest($jql))->dto() ?? [];

        return collect($issues)->map(fn (JiraIssue $issue) => $this->mapToTicket($issue));
    }

    private function resolveJiraAccountId(string $email): ?string
    {
        $cacheKey = 'jira.account_id.'.md5($email);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        /** @var array<int, JiraUser> $users */
        $users = $this->connector()->send(new GetUserRequest($email))->dto() ?? [];

        $matchingUser = collect($users)
            ->first(fn (JiraUser $u) => strcasecmp($u->emailAddress, $email) === 0);

        $accountId = $matchingUser?->accountId;

        if ($accountId !== null) {
            Cache::forever($cacheKey, $accountId);
        }

        return $accountId;
    }

    private function connector(): Connector
    {
        if (isset($this->config['integration_id'])) {
            $integration = Integration::find((int) $this->config['integration_id']);

            if ($integration) {
                $integration = app(OAuthService::class)->refreshIfExpired($integration);
                $this->config = array_merge($this->config, $integration->config ?? []);
            }
        }

        return new Connector($this->config);
    }

    private function escapeJqlString(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function mapToTicket(JiraIssue $issue): Ticket
    {
        return new Ticket(
            type: $issue->issueType,
            id: $issue->key,
            number: $issue->key,
            title: $issue->summary,
            created_at: $issue->createdAt,
            closed_at: $issue->resolvedAt,
            url: ($this->config['cloud_url'] ?? '').'/browse/'.$issue->key,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $renderedComments
     * @param  array<int, array<string, mixed>>  $rawComments
     * @return Collection<int, JiraComment>
     */
    private function mapComments(array $renderedComments, array $rawComments): Collection
    {
        $renderedById = collect($renderedComments)->keyBy('id');

        return collect($rawComments)->map(function (array $comment) use ($renderedById): JiraComment {
            $rendered = $renderedById->get($comment['id']);
            $body = $rendered ? strip_tags((string) $rendered['body']) : ($comment['body'] ?? '');

            return new JiraComment(
                id: $comment['id'],
                authorDisplayName: $comment['author']['displayName'] ?? '',
                authorEmail: $comment['author']['emailAddress'] ?? null,
                body: trim($body),
                createdAt: CarbonImmutable::parse($comment['created']),
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $histories
     * @return Collection<int, JiraChangelogEntry>
     */
    private function mapChangelog(array $histories): Collection
    {
        $entries = collect();

        foreach ($histories as $history) {
            foreach ($history['items'] ?? [] as $index => $item) {
                if ($item['field'] !== 'status') {
                    continue;
                }

                $entries->push(new JiraChangelogEntry(
                    id: $history['id'].'-'.$index,
                    authorDisplayName: $history['author']['displayName'] ?? '',
                    authorEmail: $history['author']['emailAddress'] ?? null,
                    fromStatus: $item['fromString'] ?? '',
                    toStatus: $item['toString'] ?? '',
                    createdAt: CarbonImmutable::parse($history['created']),
                ));
            }
        }

        return $entries;
    }

    /**
     * @param  Collection<int, JiraComment>  $comments
     * @param  Collection<int, JiraChangelogEntry>  $changelogEntries
     * @return Collection<int, TicketAction>
     */
    private function mergeActions(Collection $comments, Collection $changelogEntries): Collection
    {
        $commentActions = $comments->map(fn (JiraComment $comment) => new TicketAction(
            id: 'comment-'.$comment->id,
            entryDate: $comment->createdAt,
            text: $comment->body,
            personDisplayName: $comment->authorDisplayName ?: null,
            personEmail: $comment->authorEmail,
        ));

        $changelogActions = $changelogEntries->map(fn (JiraChangelogEntry $entry) => new TicketAction(
            id: 'changelog-'.$entry->id,
            entryDate: $entry->createdAt,
            text: "Status changed from '$entry->fromStatus' to '$entry->toStatus'",
            personDisplayName: $entry->authorDisplayName ?: null,
            personEmail: $entry->authorEmail,
        ));

        return $commentActions->merge($changelogActions)
            ->filter(fn (TicketAction $action) => $action->text !== '')
            ->sortBy(fn (TicketAction $action) => $action->entryDate->timestamp)
            ->values();
    }
}
