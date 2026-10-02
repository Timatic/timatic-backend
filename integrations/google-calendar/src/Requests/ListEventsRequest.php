<?php

namespace Timatic\GoogleCalendar\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GoogleCalendar\DataTransferObjects\CalendarEvent;

class ListEventsRequest extends Request
{
    /**
     * How far back to look for started events. Wider than the sync schedule interval
     * so a delayed or skipped run still gets re-covered by the next one; dedup on
     * Event.external_id makes re-fetching already-synced events safe.
     */
    public const LOOKBACK_MINUTES = 60;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/calendars/primary/events';
    }

    /**
     * A cancelled event and one without a start time carry nothing a time entry could be made from,
     * so they never become a CalendarEvent in the first place.
     *
     * @return Collection<int, CalendarEvent>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        return new Collection($response->json('items') ?? [])
            ->reject(fn (array $item): bool => ($item['status'] ?? '') === 'cancelled')
            ->filter(fn (array $item): bool => isset($item['start']['dateTime']))
            ->map(fn (array $item): CalendarEvent => CalendarEvent::fromApiResponse($item))
            ->values();
    }

    protected function defaultQuery(): array
    {
        return [
            'timeMin' => now()->subMinutes(self::LOOKBACK_MINUTES)->toRfc3339String(),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 100,
        ];
    }
}
