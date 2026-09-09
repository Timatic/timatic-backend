<?php

namespace Timatic\GitHub\Jobs;

use App\DataTransferObjects\Ticket;
use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\ServiceProvider;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, string> */
    private const array REPOSITORY_ACTIONS = [
        'created',
        'deleted',
        'archived',
        'unarchived',
        'publicized',
        'privatized',
        'edited',
        'renamed',
        'transferred',
    ];

    /** @var array<string, string> */
    private const array REVIEW_STATE_MAP = [
        'approved' => 'pr_approved',
        'changes_requested' => 'pr_changes_requested',
        'commented' => 'pr_commented',
    ];

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array $payload,
        private readonly ?RepositoryMapping $mapping,
        private readonly string $eventName,
        private readonly ?string $action = null,
    ) {}

    public function handle(TicketService $ticketService): void
    {
        match ($this->eventName) {
            'push' => $this->handlePush($ticketService),
            'pull_request' => $this->handlePullRequest($ticketService),
            'pull_request_review' => $this->handleReview($ticketService),
            'pull_request_review_comment' => $this->handleReviewComment($ticketService),
            'issue_comment' => $this->handleIssueComment($ticketService),
            'create' => $this->handleRefCreated($ticketService),
            'repository' => $this->handleRepository(),
            default => null,
        };
    }

    private function handlePush(TicketService $ticketService): void
    {
        $branchName = $this->branchName();
        $forced = (bool) ($this->payload['forced'] ?? false);
        $knownCommits = [];

        foreach ($this->payload['commits'] ?? [] as $commit) {
            if ($this->isDuplicateDelivery($commit)) {
                continue;
            }

            if ($this->isKnownCommit($commit, $forced)) {
                $knownCommits[] = $commit;

                continue;
            }

            $this->createEventFromCommit($commit, $branchName, $ticketService);
        }

        if ($knownCommits !== []) {
            $this->createRebaseEvent($knownCommits, $branchName);
        }
    }

    private function handlePullRequest(TicketService $ticketService): void
    {
        $pullRequest = $this->payload['pull_request'] ?? [];
        $eventTypeId = match (true) {
            in_array($this->action, ['opened', 'reopened'], strict: true) => 'pr_opened',
            $this->action === 'closed' && ($pullRequest['merged'] ?? false) => 'pr_merged',
            $this->action === 'closed' => 'pr_declined',
            default => null,
        };

        if ($eventTypeId === null) {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $title = (string) ($pullRequest['title'] ?? '');
        $timestamp = $pullRequest['updated_at'] ?? $pullRequest['created_at'] ?? null;

        $this->createEvent(
            user: $user,
            eventTypeId: $eventTypeId,
            title: $title,
            timestamp: $this->parseTimestamp($timestamp),
            ticket: $this->findTicket($ticketService, ...array_filter([$title, (string) ($pullRequest['head']['ref'] ?? '')])),
            externalId: 'pr:'.$eventTypeId.':'.($pullRequest['id'] ?? '').':'.$timestamp,
        );
    }

    private function handleReview(TicketService $ticketService): void
    {
        $review = $this->payload['review'] ?? [];
        $eventTypeId = self::REVIEW_STATE_MAP[$review['state'] ?? ''] ?? null;

        if ($this->action !== 'submitted' || $eventTypeId === null) {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $pullRequest = $this->payload['pull_request'] ?? [];
        $title = (string) ($pullRequest['title'] ?? '');

        $this->createEvent(
            user: $user,
            eventTypeId: $eventTypeId,
            title: $title,
            timestamp: $this->parseTimestamp($review['submitted_at'] ?? null),
            ticket: $this->findTicket($ticketService, ...array_filter([$title, (string) ($pullRequest['head']['ref'] ?? '')])),
            description: $this->commentBody($review['body'] ?? null),
            externalId: 'review:'.($review['id'] ?? ''),
        );
    }

    private function handleReviewComment(TicketService $ticketService): void
    {
        if ($this->action !== 'created') {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $comment = $this->payload['comment'] ?? [];
        $pullRequest = $this->payload['pull_request'] ?? [];
        $title = (string) ($pullRequest['title'] ?? '');
        $body = $this->commentBody($comment['body'] ?? null);

        $this->createEvent(
            user: $user,
            eventTypeId: 'pr_commented',
            title: $title,
            timestamp: $this->parseTimestamp($comment['created_at'] ?? null),
            ticket: $this->findTicket($ticketService, ...array_filter([$title, (string) $body])),
            description: $body,
            externalId: 'comment:'.($comment['id'] ?? ''),
        );
    }

    private function handleIssueComment(TicketService $ticketService): void
    {
        if ($this->action !== 'created') {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $issue = $this->payload['issue'] ?? [];
        $comment = $this->payload['comment'] ?? [];
        $title = (string) ($issue['title'] ?? '');
        $body = $this->commentBody($comment['body'] ?? null);

        $this->createEvent(
            user: $user,
            eventTypeId: isset($issue['pull_request']) ? 'pr_commented' : 'issue_commented',
            title: $title,
            timestamp: $this->parseTimestamp($comment['created_at'] ?? null),
            ticket: $this->findTicket($ticketService, ...array_filter([$title, (string) $body])),
            description: $body,
            externalId: 'comment:'.($comment['id'] ?? ''),
        );
    }

    private function handleRefCreated(TicketService $ticketService): void
    {
        $refType = $this->payload['ref_type'] ?? null;
        $eventTypeId = match ($refType) {
            'branch' => 'branch_created',
            'tag' => 'tag_created',
            default => null,
        };

        if ($eventTypeId === null) {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $ref = (string) ($this->payload['ref'] ?? '');

        $this->createEvent(
            user: $user,
            eventTypeId: $eventTypeId,
            title: 'Created '.$refType.' '.$ref,
            // the create event carries no timestamp of its own
            timestamp: Carbon::now()->utc(),
            ticket: $this->findTicket($ticketService, $ref),
            externalId: 'create:'.$this->repositoryFullName().':'.$ref,
        );
    }

    private function handleRepository(): void
    {
        if (! in_array($this->action, self::REPOSITORY_ACTIONS, strict: true)) {
            return;
        }

        $user = $this->senderUser();

        if ($user === null) {
            return;
        }

        $repository = $this->payload['repository'] ?? [];
        $timestamp = $repository['updated_at'] ?? null;

        $this->createEvent(
            user: $user,
            eventTypeId: 'repository_'.$this->action,
            title: $this->repositoryTitle(),
            timestamp: $this->parseTimestamp($timestamp),
            ticket: null,
            externalId: 'repository:'.$this->action.':'.($repository['id'] ?? '').':'.$timestamp,
        );
    }

    /** @param array<string, mixed> $commit */
    private function createEventFromCommit(array $commit, ?string $branchName, TicketService $ticketService): void
    {
        $email = $commit['author']['email'] ?? null;

        if (! is_string($email)) {
            return;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $this->learnGithubLogin($user, $commit);

        $timestamp = $commit['timestamp'] ?? null;

        if (! is_string($timestamp)) {
            return;
        }

        $message = (string) ($commit['message'] ?? '');
        [$commitTitle, $commitDescription] = $this->splitCommitMessage($message);

        $this->createEvent(
            user: $user,
            eventTypeId: 'commit_pushed',
            title: $commitTitle,
            timestamp: Carbon::parse($timestamp)->utc(),
            ticket: $this->findTicket($ticketService, ...array_filter([$message, $branchName])),
            description: $commitDescription,
            externalId: $this->commitExternalId($commit),
        );
    }

    private function createEvent(User $user, string $eventTypeId, string $title, Carbon $timestamp, ?Ticket $ticket, ?string $description = null, ?string $externalId = null): void
    {
        $attributes = [
            'user_id' => $user->id,
            'source_id' => ServiceProvider::SOURCE_ID,
            'event_type_id' => $eventTypeId,
            'customer_id' => $this->mapping->customer_id ?? $ticket?->customer_id,
            'budget_id' => $this->mapping->budget_id ?? $ticket?->budget_id,
            'title' => mb_substr($title, 0, 255),
            'description' => $description,
            // webhook events are point events: the moment is known but the lead-up isn't,
            // so started_at stays null and activity creation estimates the duration
            'started_at' => null,
            'ended_at' => $timestamp,
            'ticket_id' => $ticket?->id,
            'ticket_number' => $ticket?->number,
            'ticket_type' => $ticket?->type,
        ];

        if ($externalId !== null) {
            Event::firstOrCreate(['source_id' => ServiceProvider::SOURCE_ID, 'external_id' => $externalId], $attributes);

            return;
        }

        Event::create($attributes);
    }

    /** @param non-empty-list<array<string, mixed>> $knownCommits */
    private function createRebaseEvent(array $knownCommits, ?string $branchName): void
    {
        $email = $knownCommits[0]['author']['email'] ?? null;
        $user = is_string($email) ? User::where('email', $email)->first() : null;

        if ($user === null) {
            return;
        }

        $timestamp = collect($knownCommits)
            ->map(fn (array $commit) => Carbon::parse($commit['timestamp'])->utc())
            ->max();

        $title = sprintf('Rebased %d commits on %s', count($knownCommits), $branchName ?? 'unknown branch');

        if ($this->eventExists($user, 'rebase', $title, $timestamp)) {
            return;
        }

        $this->createEvent(
            user: $user,
            eventTypeId: 'rebase',
            title: $title,
            timestamp: $timestamp,
            ticket: null,
        );
    }

    /** @param array<string, mixed> $commit */
    private function isDuplicateDelivery(array $commit): bool
    {
        $externalId = $this->commitExternalId($commit);

        return $externalId !== null && Event::query()
            ->where('source_id', ServiceProvider::SOURCE_ID)
            ->where('external_id', $externalId)
            ->exists();
    }

    /**
     * A force-pushed commit may carry a rewritten committer date (e.g. after a `git rebase`),
     * so on forced pushes we match by title alone; on normal pushes we still require the date
     * to match, since recurring titles (e.g. "composer update") are otherwise legitimate new commits.
     *
     * @param  array<string, mixed>  $commit
     */
    private function isKnownCommit(array $commit, bool $forced): bool
    {
        $email = $commit['author']['email'] ?? null;
        $timestamp = $commit['timestamp'] ?? null;

        if (! is_string($email) || ! is_string($timestamp)) {
            return false;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        [$commitTitle] = $this->splitCommitMessage((string) ($commit['message'] ?? ''));

        return $this->eventExists($user, 'commit_pushed', $commitTitle, $forced ? null : Carbon::parse($timestamp)->utc());
    }

    private function eventExists(User $user, string $eventTypeId, string $title, ?Carbon $timestamp): bool
    {
        return Event::query()
            ->where('user_id', $user->id)
            ->where('source_id', ServiceProvider::SOURCE_ID)
            ->where('event_type_id', $eventTypeId)
            ->where('title', mb_substr($title, 0, 255))
            ->when($timestamp !== null, fn ($query) => $query->where('ended_at', $timestamp))
            ->exists();
    }

    /** @param array<string, mixed> $commit */
    private function commitExternalId(array $commit): ?string
    {
        $repositoryFullName = $this->repositoryFullName();
        $sha = (string) ($commit['id'] ?? '');

        return $repositoryFullName !== '' && $sha !== '' ? $repositoryFullName.'@'.$sha : null;
    }

    /** @param array<string, mixed> $commit */
    private function learnGithubLogin(User $user, array $commit): void
    {
        if ($user->github_login !== null) {
            return;
        }

        $login = $commit['author']['username'] ?? null;

        if (is_string($login)) {
            $user->github_login = $login;
            $user->save();
        }
    }

    private function senderUser(): ?User
    {
        $login = $this->payload['sender']['login'] ?? null;

        return is_string($login) ? User::where('github_login', $login)->first() : null;
    }

    private function findTicket(TicketService $ticketService, string ...$texts): ?Ticket
    {
        foreach ($ticketService->ticketKeyPatterns() as $pattern) {
            foreach ($texts as $text) {
                if (preg_match('/\b('.$pattern.')\b/i', $text, $matches)) {
                    $key = $matches[1];

                    return $ticketService->fetchTicketByKey(str_contains($key, '#') ? $key : strtoupper($key));
                }
            }
        }

        return $this->findIssueReference($ticketService, ...$texts);
    }

    /**
     * A bare "#123" only identifies an issue together with the repository it was
     * delivered for, so the reference is expanded before it is looked up.
     */
    private function findIssueReference(TicketService $ticketService, string ...$texts): ?Ticket
    {
        $repositoryFullName = $this->repositoryFullName();

        if ($repositoryFullName === '') {
            return null;
        }

        foreach ($texts as $text) {
            if (preg_match('/#(\d+)/', $text, $matches)) {
                return $ticketService->fetchTicketByKey($repositoryFullName.'#'.$matches[1]);
            }
        }

        return null;
    }

    /** @return array{string, ?string} */
    private function splitCommitMessage(string $message): array
    {
        $lines = explode("\n", trim($message), 2);
        $title = trim($lines[0]);
        $description = isset($lines[1]) ? trim($lines[1]) : null;

        return [$title, $description ?: null];
    }

    private function commentBody(?string $body): ?string
    {
        $body = trim((string) $body);

        return $body === '' ? null : $body;
    }

    private function repositoryTitle(): string
    {
        $repositoryFullName = $this->repositoryFullName();
        $previousName = $this->payload['changes']['repository']['name']['from'] ?? null;

        if ($this->action === 'renamed' && is_string($previousName)) {
            return 'Repository renamed: '.$previousName.' to '.$repositoryFullName;
        }

        return 'Repository '.$this->action.': '.$repositoryFullName;
    }

    private function repositoryFullName(): string
    {
        return (string) ($this->payload['repository']['full_name'] ?? '');
    }

    private function branchName(): ?string
    {
        $ref = $this->payload['ref'] ?? null;

        if (! is_string($ref) || ! str_starts_with($ref, 'refs/heads/')) {
            return null;
        }

        return substr($ref, strlen('refs/heads/'));
    }

    private function parseTimestamp(mixed $timestamp): Carbon
    {
        return is_string($timestamp) ? Carbon::parse($timestamp)->utc() : Carbon::now()->utc();
    }
}
