<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\Tickets\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Timatic\GitHub\ServiceProvider;
use Timatic\GitHub\UserMatcher;

class ProcessPushWebhookJob extends ProcessWebhookJob
{
    public function handle(TicketService $ticketService): void
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

    /** @param array<string, mixed> $commit */
    private function createEventFromCommit(array $commit, ?string $branchName, TicketService $ticketService): void
    {
        $user = $this->commitAuthor($commit);

        if ($user === null) {
            return;
        }

        $timestamp = $commit['timestamp'] ?? null;

        if (! is_string($timestamp)) {
            return;
        }

        $message = (string) ($commit['message'] ?? '');
        [$commitTitle, $commitDescription] = $this->splitCommitMessage($message);

        $this->createEvent(
            user: $user,
            eventTypeId: 'commit_created',
            title: $commitTitle,
            timestamp: Carbon::parse($timestamp)->utc(),
            ticket: $this->findTicket($ticketService, ...array_filter([$message, $branchName])),
            description: $commitDescription,
            externalId: $this->commitExternalId($commit),
        );
    }

    /** @param non-empty-list<array<string, mixed>> $knownCommits */
    private function createRebaseEvent(array $knownCommits, ?string $branchName): void
    {
        $user = $this->commitAuthor($knownCommits[0]);

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
        $timestamp = $commit['timestamp'] ?? null;

        if (! is_string($timestamp)) {
            return false;
        }

        $user = $this->commitAuthor($commit);

        if ($user === null) {
            return false;
        }

        [$commitTitle] = $this->splitCommitMessage((string) ($commit['message'] ?? ''));

        return $this->eventExists($user, 'commit_created', $commitTitle, $forced ? null : Carbon::parse($timestamp)->utc());
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
    private function commitAuthor(array $commit): ?User
    {
        $email = $commit['author']['email'] ?? null;
        $login = $commit['author']['username'] ?? null;

        return app(UserMatcher::class)->forCommitAuthor(
            is_string($email) ? $email : null,
            is_string($login) ? $login : null,
        );
    }

    /** @return array{string, ?string} */
    private function splitCommitMessage(string $message): array
    {
        $lines = explode("\n", trim($message), 2);
        $title = trim($lines[0]);
        $description = isset($lines[1]) ? trim($lines[1]) : null;

        return [$title, $description ?: null];
    }

    private function branchName(): ?string
    {
        $ref = $this->payload['ref'] ?? null;

        if (! is_string($ref) || ! str_starts_with($ref, 'refs/heads/')) {
            return null;
        }

        return substr($ref, strlen('refs/heads/'));
    }
}
