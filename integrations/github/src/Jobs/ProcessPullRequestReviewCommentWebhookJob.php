<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\TicketService;

class ProcessPullRequestReviewCommentWebhookJob extends ProcessWebhookJob
{
    public function handle(TicketService $ticketService): void
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
}
