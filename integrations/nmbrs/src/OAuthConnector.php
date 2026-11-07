<?php

declare(strict_types=1);

namespace Timatic\Nmbrs;

use Saloon\Http\Connector as SaloonConnector;

class OAuthConnector extends SaloonConnector
{
    public function resolveBaseUrl(): string
    {
        return 'https://identityservice.nmbrs.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }
}
