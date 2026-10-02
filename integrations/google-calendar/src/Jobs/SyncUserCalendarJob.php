<?php

namespace Timatic\GoogleCalendar\Jobs;

use App\DataTransferObjects\Ticket;
use App\Integrations\TicketService;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Timatic\GoogleCalendar\Connector;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\ListEventsRequest;
use Timatic\GoogleCalendar\ServiceProvider;

class SyncUserCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly GoogleCalendarConnection $calendarConnection) {}

    public function handle(OAuthService $oauthService, TicketService $ticketService): void
    {
        $connection = $oauthService->refreshIfExpired($this->calendarConnection);

        if (! $connection->exists) {
            return;
        }

        $response = new Connector((string) $connection->access_token)->send(new ListEventsRequest);

        if ($response->failed()) {
            return;
        }

        $lookbackStartsAt = now()->subMinutes(ListEventsRequest::LOOKBACK_MINUTES);

        foreach ($response->dto() as $calendarEvent) {
            if ($calendarEvent->isPrivate()) {
                continue;
            }

            if (! $calendarEvent->startedAt->between($lookbackStartsAt, now())) {
                continue;
            }

            $ticket = $this->findTicket($ticketService, $calendarEvent->title, $calendarEvent->description ?? '');

            Event::firstOrCreate([
                'source_id' => ServiceProvider::SOURCE_ID,
                'external_id' => $calendarEvent->googleEventId,
            ], [
                'user_id' => $connection->user_id,
                'event_type_id' => ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED,
                'title' => mb_substr($calendarEvent->title, 0, 255),
                'ticket_id' => $ticket?->id,
                'ticket_number' => $ticket?->number,
                'ticket_type' => $ticket?->type,
                'customer_id' => $ticket?->customer_id,
                'budget_id' => $ticket?->budget_id,
                'started_at' => $calendarEvent->startedAt,
                'ended_at' => $calendarEvent->endedAt,
            ]);
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
}
