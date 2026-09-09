<?php

namespace Timatic\GitHub;

use App\DataTransferObjects\Ticket;
use App\DataTransferObjects\TicketAction;
use App\Integrations\Contracts\TicketProviderInterface;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Timatic\GitHub\DataTransferObjects\GitHubIssue;
use Timatic\GitHub\DataTransferObjects\GitHubIssueComment;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetIssueCommentsRequest;
use Timatic\GitHub\Requests\GetIssueRequest;
use Timatic\GitHub\Requests\GetIssuesRequest;
use Timatic\GitHub\Requests\SearchIssuesRequest;

final class TicketProvider implements TicketProviderInterface
{
    /**
     * GitHub's search query has a length limit, so only the most recently updated
     * mappings are used as `repo:` qualifiers.
     */
    private const SEARCHABLE_REPOSITORIES = 20;

    private const TICKET_TYPE = 'issue';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        return new self($config);
    }

    public static function ticketKeyPattern(): string
    {
        return '[\w.-]+\/[\w.-]+#\d+';
    }

    /** @return Collection<int, Ticket> */
    public function searchTickets(?Customer $customer, ?string $search = null, ?User $user = null): Collection
    {
        if ($this->installationId() === null) {
            return new Collection;
        }

        if ($search !== null && preg_match('/^'.self::ticketKeyPattern().'$/', $search) === 1) {
            $ticket = $this->fetchTicketByKey($search);

            return $ticket === null ? new Collection : new Collection([$ticket]);
        }

        $repositories = $this->mappedRepositories($customer);

        if ($repositories->isEmpty()) {
            return new Collection;
        }

        if ($search === null || $search === '') {
            return $customer === null
                ? $this->searchIssuesInvolving($user, $repositories)
                : $this->fetchOpenIssues($repositories);
        }

        return $this->searchIssues($search, $repositories);
    }

    public function fetchTicketByKey(string $key): ?Ticket
    {
        $issue = $this->fetchIssue($key);

        return $issue === null ? null : $this->mapToTicket($issue);
    }

    public function fetchTicketDetails(string $key): ?Ticket
    {
        $issue = $this->fetchIssue($key);

        if ($issue === null) {
            return null;
        }

        [$owner, $repository, $number] = $this->splitKey($key);

        /** @var array<int, GitHubIssueComment> $comments */
        $comments = $this->connector()
            ->send(new GetIssueCommentsRequest($owner, $repository, $number))
            ->dto();

        return $this->mapToTicket($issue, $this->mapComments($comments));
    }

    /**
     * @param  Collection<int, RepositoryMapping>  $repositories
     * @return Collection<int, Ticket>
     */
    private function fetchOpenIssues(Collection $repositories): Collection
    {
        return $repositories
            ->flatMap(function (RepositoryMapping $mapping): array {
                $response = $this->connector()
                    ->send(new GetIssuesRequest($mapping->owner_login, $mapping->repository_name));

                return $response->failed() ? [] : $response->dto();
            })
            ->pipe(fn (Collection $issues) => $this->mapToTickets($issues));
    }

    /**
     * @param  Collection<int, RepositoryMapping>  $repositories
     * @return Collection<int, Ticket>
     */
    private function searchIssues(string $search, Collection $repositories): Collection
    {
        return $this->runSearch('is:issue '.$search.' '.$this->repositoryQualifiers($repositories));
    }

    /**
     * @param  Collection<int, RepositoryMapping>  $repositories
     * @return Collection<int, Ticket>
     */
    private function searchIssuesInvolving(?User $user, Collection $repositories): Collection
    {
        if ($user?->github_login === null) {
            return new Collection;
        }

        return $this->runSearch(
            'is:issue is:open involves:'.$user->github_login.' '.$this->repositoryQualifiers($repositories)
        );
    }

    /** @return Collection<int, Ticket> */
    private function runSearch(string $query): Collection
    {
        $response = $this->connector()->send(new SearchIssuesRequest($query));

        return $response->failed() ? new Collection : $this->mapToTickets(new Collection($response->dto()));
    }

    /** @param Collection<int, RepositoryMapping> $repositories */
    private function repositoryQualifiers(Collection $repositories): string
    {
        return $repositories
            ->take(self::SEARCHABLE_REPOSITORIES)
            ->map(fn (RepositoryMapping $mapping) => 'repo:'.$mapping->repository_full_name)
            ->implode(' ');
    }

    /** @return Collection<int, RepositoryMapping> */
    private function mappedRepositories(?Customer $customer): Collection
    {
        return RepositoryMapping::query()
            ->where('integration_id', $this->config['integration_id'] ?? 0)
            ->when($customer, fn ($query, Customer $customer) => $query->where('customer_id', $customer->id))
            ->active()
            ->orderByDesc('updated_at')
            ->get();
    }

    private function fetchIssue(string $key): ?GitHubIssue
    {
        if ($this->installationId() === null) {
            return null;
        }

        [$owner, $repository, $number] = $this->splitKey($key);

        if ($owner === '' || $repository === '' || $number === 0) {
            return null;
        }

        $response = $this->connector()->send(new GetIssueRequest($owner, $repository, $number));

        if ($response->failed()) {
            return null;
        }

        /** @var GitHubIssue $issue */
        $issue = $response->dto();

        return $issue->isPullRequest ? null : $issue;
    }

    /**
     * @param  Collection<int, GitHubIssue>  $issues
     * @return Collection<int, Ticket>
     */
    private function mapToTickets(Collection $issues): Collection
    {
        return $issues
            ->reject(fn (GitHubIssue $issue) => $issue->isPullRequest)
            ->map(fn (GitHubIssue $issue) => $this->mapToTicket($issue))
            ->values();
    }

    /** @param Collection<int, TicketAction> $actions */
    private function mapToTicket(GitHubIssue $issue, ?Collection $actions = null): Ticket
    {
        $mapping = RepositoryMapping::query()
            ->where('integration_id', $this->config['integration_id'] ?? 0)
            ->where('repository_full_name', $issue->repositoryFullName)
            ->active()
            ->first();

        $ticket = new Ticket(
            type: self::TICKET_TYPE,
            id: $issue->key(),
            number: $issue->key(),
            title: $issue->title,
            created_at: $issue->createdAt,
            closed_at: $issue->closedAt,
            url: $issue->htmlUrl,
            actions: $actions ?? new Collection,
        );

        return $ticket->withMapping($mapping?->customer_id, $mapping?->budget_id);
    }

    /**
     * @param  array<int, GitHubIssueComment>  $comments
     * @return Collection<int, TicketAction>
     */
    private function mapComments(array $comments): Collection
    {
        return collect($comments)
            ->map(fn (GitHubIssueComment $comment) => new TicketAction(
                id: 'comment-'.$comment->id,
                entryDate: CarbonImmutable::parse($comment->createdAt),
                text: $comment->body,
                personDisplayName: $comment->authorLogin !== '' ? $comment->authorLogin : null,
                // GitHub does not expose the email address of an issue commenter
                personEmail: null,
            ))
            ->filter(fn (TicketAction $action) => $action->text !== '')
            ->sortBy(fn (TicketAction $action) => $action->entryDate->timestamp)
            ->values();
    }

    /** @return array{string, string, int} */
    private function splitKey(string $key): array
    {
        [$repositoryFullName, $number] = array_pad(explode('#', $key, 2), 2, '');
        [$owner, $repository] = array_pad(explode('/', $repositoryFullName, 2), 2, '');

        return [$owner, $repository, (int) $number];
    }

    private function installationId(): ?int
    {
        $installationId = $this->config['installation_id'] ?? null;

        return is_numeric($installationId) ? (int) $installationId : null;
    }

    private function connector(): Connector
    {
        return Connector::forInstallation((int) $this->installationId());
    }
}
