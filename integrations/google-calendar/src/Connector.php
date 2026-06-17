<?php

namespace Timatic\GoogleCalendar;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly string $accessToken) {}

    public function resolveBaseUrl(): string
    {
        return 'https://www.googleapis.com/calendar/v3';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->accessToken,
            'Accept' => 'application/json',
        ];
    }
}
