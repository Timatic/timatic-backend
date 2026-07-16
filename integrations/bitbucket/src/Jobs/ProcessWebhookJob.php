<?php

namespace Timatic\Bitbucket\Jobs;

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
use Timatic\Bitbucket\Models\RepositoryMapping;
use Timatic\Bitbucket\ServiceProvider;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<string, string> */
    private const array PR_EVENT_TYPE_MAP = [
        'pullrequest:comment_created' => 'pr_commented',
        'pullrequest:approved' => 'pr_approved',
        'pullrequest:changes_request_created' => 'pr_changes_requested',
        'pullrequest:fulfilled' => 'pr_merged',
        'pullrequest:rejected' => 'pr_declined',
    ];

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly array $payload,
        private readonly ?RepositoryMapping $mapping,
        private readonly string $eventKey = 'repo:push',
    ) {}

    public function handle(TicketService $ticketService): void
    {
        if ($this->eventKey === 'repo:push') {
            $this->handlePush($ticketService);
        } elseif (isset(self::PR_EVENT_TYPE_MAP[$this->eventKey])) {
            $this->handlePullRequest($ticketService);
        }
    }

    private function handlePush(TicketService $ticketService): void
    {
        foreach ($this->payload['push']['changes'] ?? [] as $change) {
            $branchName = is_string($change['new']['name'] ?? null) ? $change['new']['name'] : null;

            $knownCommits = [];
            foreach ($change['commits'] ?? [] as $commit) {
                if ($this->isKnownCommit($commit)) {
                    $knownCommits[] = $commit;

                    continue;
                }

                $this->createEventFromCommit($commit, $branchName, $ticketService);
            }

            if ($knownCommits !== []) {
                $this->createRebaseEvent($knownCommits, $branchName);
            }
        }
    }

    private function handlePullRequest(TicketService $ticketService): void
    {
        $accountId = $this->payload['actor']['account_id'] ?? null;

        if (! is_string($accountId)) {
            return;
        }

        $user = User::where('bitbucket_account_id', $accountId)->first();

        if ($user === null) {
            return;
        }

        $pullRequest = $this->payload['pullrequest'] ?? [];
        $title = (string) ($pullRequest['title'] ?? '');
        $branchName = is_string($pullRequest['source']['branch']['name'] ?? null)
            ? $pullRequest['source']['branch']['name']
            : null;

        $this->createEvent(
            user: $user,
            eventTypeId: self::PR_EVENT_TYPE_MAP[$this->eventKey],
            title: $title,
            timestamp: Carbon::parse($pullRequest['updated_on'] ?? $pullRequest['created_on'] ?? null)->utc(),
            ticket: $this->findTicket($ticketService, ...array_filter([$title, $branchName])),
        );
    }

    /** @param array<string, mixed> $commit */
    private function createEventFromCommit(array $commit, ?string $branchName, TicketService $ticketService): void
    {
        $email = $this->extractEmail($commit['author']['raw'] ?? '');

        if ($email === null) {
            return;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $this->learnBitbucketAccountId($user, $commit);

        $date = $commit['date'] ?? null;

        if (! is_string($date)) {
            return;
        }

        $message = (string) ($commit['message'] ?? '');
        [$commitTitle, $commitDescription] = $this->splitCommitMessage($message);

        $this->createEvent(
            user: $user,
            eventTypeId: 'commit_pushed',
            title: $commitTitle,
            timestamp: Carbon::parse($date)->utc(),
            ticket: $this->findTicket($ticketService, ...array_filter([$message, $branchName])),
            description: $commitDescription,
        );
    }

    private function createEvent(User $user, string $eventTypeId, string $title, Carbon $timestamp, ?Ticket $ticket, ?string $description = null): void
    {
        if ($ticket === null && $this->mapping?->customer_id === null) {
            return;
        }

        $customerId = $this->mapping->customer_id ?? $ticket?->customer_id;
        $budgetId = $this->mapping->budget_id ?? $ticket?->budget_id;

        Event::create([
            'user_id' => $user->id,
            'source_id' => ServiceProvider::SOURCE_ID,
            'event_type_id' => $eventTypeId,
            'customer_id' => $customerId,
            'budget_id' => $budgetId,
            'title' => mb_substr($title, 0, 255),
            'description' => $description,
            'started_at' => $timestamp,
            'ended_at' => $timestamp,
            'ticket_id' => $ticket?->id,
            'ticket_number' => $ticket?->number,
            'ticket_type' => $ticket?->type,
        ]);
    }

    /** @param array<string, mixed> $commit */
    private function isKnownCommit(array $commit): bool
    {
        $email = $this->extractEmail($commit['author']['raw'] ?? '');
        $date = $commit['date'] ?? null;

        if ($email === null || ! is_string($date)) {
            return false;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            return false;
        }

        [$commitTitle] = $this->splitCommitMessage((string) ($commit['message'] ?? ''));

        return $this->eventExists($user, 'commit_pushed', $commitTitle, Carbon::parse($date)->utc());
    }

    /** @param non-empty-list<array<string, mixed>> $knownCommits */
    private function createRebaseEvent(array $knownCommits, ?string $branchName): void
    {
        $email = $this->extractEmail($knownCommits[0]['author']['raw'] ?? '');
        $user = $email === null ? null : User::where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $timestamp = collect($knownCommits)
            ->map(fn (array $commit) => Carbon::parse($commit['date'])->utc())
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

    private function eventExists(User $user, string $eventTypeId, string $title, Carbon $timestamp): bool
    {
        return Event::query()
            ->where('user_id', $user->id)
            ->where('source_id', ServiceProvider::SOURCE_ID)
            ->where('event_type_id', $eventTypeId)
            ->where('started_at', $timestamp)
            ->where('title', mb_substr($title, 0, 255))
            ->exists();
    }

    /** @return array{string, ?string} */
    private function splitCommitMessage(string $message): array
    {
        $lines = explode("\n", trim($message), 2);
        $title = trim($lines[0]);
        $description = isset($lines[1]) ? trim($lines[1]) : null;

        return [$title, $description ?: null];
    }

    /** @param array<string, mixed> $commit */
    private function learnBitbucketAccountId(User $user, array $commit): void
    {
        if ($user->bitbucket_account_id !== null) {
            return;
        }

        $accountId = $commit['author']['user']['account_id'] ?? null;

        if (is_string($accountId)) {
            $user->bitbucket_account_id = $accountId;
            $user->save();
        }
    }

    private function findTicket(TicketService $ticketService, string ...$texts): ?Ticket
    {
        foreach ($ticketService->ticketKeyPatterns() as $pattern) {
            foreach ($texts as $text) {
                if (preg_match('/\b('.$pattern.')\b/i', $text, $matches)) {
                    return $ticketService->fetchTicketByKey(strtoupper($matches[1]));
                }
            }
        }

        return null;
    }

    private function extractEmail(string $raw): ?string
    {
        if (preg_match('/<(.+?)>/', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
