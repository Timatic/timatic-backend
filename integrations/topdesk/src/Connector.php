<?php

namespace Timatic\Topdesk;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly ApiCredentials $credentials) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->credentials->baseUrl, '/').'/tas/api';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($this->credentials->username.':'.$this->credentials->apiToken),
            'Accept' => 'application/json',
        ];
    }
}
