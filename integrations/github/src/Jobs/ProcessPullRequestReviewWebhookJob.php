<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\Tickets\TicketService;

class ProcessPullRequestReviewWebhookJob extends ProcessWebhookJob
{
    /** @var array<string, string> */
    private const array REVIEW_STATE_MAP = [
        'approved' => 'pr_approved',
        'changes_requested' => 'pr_changes_requested',
        'commented' => 'pr_commented',
    ];

    public function handle(TicketService $ticketService): void
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
}
