<?php

namespace Timatic\Bitbucket;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials) {}

    public function resolveBaseUrl(): string
    {
        return 'https://api.bitbucket.org/2.0';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->credentials['access_token'],
            'Accept' => 'application/json',
        ];
    }
}
