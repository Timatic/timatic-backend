<?php

namespace Timatic\GoogleCalendar;

use Saloon\Http\Connector as SaloonConnector;

class OAuthConnector extends SaloonConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://oauth2.googleapis.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
