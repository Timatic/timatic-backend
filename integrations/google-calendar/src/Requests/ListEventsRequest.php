<?php

namespace Timatic\GoogleCalendar\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ListEventsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/calendars/primary/events';
    }

    protected function defaultQuery(): array
    {
        return [
            'timeMin' => now()->subMinutes(15)->toRfc3339String(),
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 100,
        ];
    }
}
