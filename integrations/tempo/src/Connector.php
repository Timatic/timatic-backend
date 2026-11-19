<?php

namespace Timatic\Tempo;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly string $apiToken) {}

    public function resolveBaseUrl(): string
    {
        return 'https://api.tempo.io';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/json',
        ];
    }
}
