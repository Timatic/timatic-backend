<?php

declare(strict_types=1);

namespace Timatic\Gmail;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly string $accessToken) {}

    public function resolveBaseUrl(): string
    {
        return 'https://gmail.googleapis.com/gmail/v1/users/me';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->accessToken,
            'Accept' => 'application/json',
        ];
    }
}
