<?php

namespace Timatic\Topdesk;

use Saloon\Contracts\Authenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    public function __construct(private readonly ApiCredentials $credentials) {}

    public function resolveBaseUrl(): string
    {
        return rtrim($this->credentials->baseUrl, '/').'/tas/api';
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator($this->credentials->username, $this->credentials->apiToken);
    }

    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
