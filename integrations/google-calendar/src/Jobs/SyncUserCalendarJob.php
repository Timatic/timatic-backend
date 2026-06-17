<?php

namespace Timatic\GoogleCalendar\Jobs;

use App\DataTransferObjects\Ticket;
use App\Integrations\TicketService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Timatic\GoogleCalendar\Connector;
use Timatic\GoogleCalendar\DataTransferObjects\CalendarEvent;
use Timatic\GoogleCalendar\OAuthService;
use Timatic\GoogleCalendar\Requests\ListEventsRequest;
use Timatic\GoogleCalendar\ServiceProvider;

class SyncUserCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly User $user) {}

    public function handle(OAuthService $oauthService, TicketService $ticketService): void
    {
        $user = $oauthService->refreshIfExpired($this->user);

        if (! $user->isOAuthConnected()) {
            return;
        }

        $response = (new Connector((string) $user->oauth_access_token))
            ->send(new ListEventsRequest);

        if ($response->failed()) {
            return;
        }

        $data = $response->json();

        foreach ($data['items'] ?? [] as $item) {
            if (($item['status'] ?? '') === 'cancelled' || ! isset($item['start']['dateTime'])) {
                continue;
            }

            $calendarEvent = CalendarEvent::fromApiResponse($item);

            if (! $calendarEvent->startedAt->between(now()->subMinutes(15), now())) {
                continue;
            }

            $ticket = $this->findTicket($ticketService, $calendarEvent->title, $calendarEvent->description ?? '');

            Event::create([
                'user_id' => $user->id,
                'source_id' => ServiceProvider::SOURCE_ID,
                'event_type_id' => ServiceProvider::EVENT_TYPE_CALENDAR_EVENT_STARTED,
                'title' => mb_substr($calendarEvent->title, 0, 255),
                'description' => $calendarEvent->description !== null
                    ? mb_substr($calendarEvent->description, 0, 65535)
                    : null,
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
