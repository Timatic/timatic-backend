<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\Tickets\TicketService;

class ProcessIssueCommentWebhookJob extends ProcessWebhookJob
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
}
