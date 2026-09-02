<?php

namespace Timatic\GoogleCalendar\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

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
