<?php

namespace Timatic\GitHub\Jobs;

use App\Integrations\Tickets\TicketService;
use Illuminate\Support\Carbon;

class ProcessCreateWebhookJob extends ProcessWebhookJob
{
    public function handle(TicketService $ticketService): void
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
}
