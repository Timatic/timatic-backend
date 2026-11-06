<?php

namespace Timatic\GitHub\Jobs;

use App\DataTransferObjects\Ticket;
use App\Integrations\Tickets\TicketService;
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
use Timatic\GitHub\UserMatcher;

/**
 * Turns one GitHub webhook delivery into Timatic events; each event name has its own job.
 */
abstract class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(
        protected readonly array $payload,
        protected readonly ?RepositoryMapping $mapping,
        protected readonly ?string $action = null,
    ) {}

    protected function createEvent(User $user, string $eventTypeId, string $title, Carbon $timestamp, ?Ticket $ticket, ?string $description = null, ?string $externalId = null): void
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

    protected function senderUser(): ?User
    {
        $login = $this->payload['sender']['login'] ?? null;

        return app(UserMatcher::class)->forLogin(is_string($login) ? $login : null);
    }

    protected function findTicket(TicketService $ticketService, string ...$texts): ?Ticket
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
    protected function findIssueReference(TicketService $ticketService, string ...$texts): ?Ticket
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

    protected function commentBody(?string $body): ?string
    {
        $body = trim((string) $body);

        return $body === '' ? null : $body;
    }

    protected function repositoryFullName(): string
    {
        return (string) ($this->payload['repository']['full_name'] ?? '');
    }

    protected function parseTimestamp(mixed $timestamp): Carbon
    {
        return is_string($timestamp) ? Carbon::parse($timestamp)->utc() : Carbon::now()->utc();
    }
}
