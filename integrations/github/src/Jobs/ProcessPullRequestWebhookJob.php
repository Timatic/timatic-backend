<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\Tickets\TicketService;

class ProcessPullRequestWebhookJob extends ProcessWebhookJob
{
    public function handle(TicketService $ticketService): void
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
}
