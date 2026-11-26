<?php

namespace Timatic\Bitbucket;

use Saloon\Http\Connector as SaloonConnector;

class Connector extends SaloonConnector
{
    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials) {}

    /** @param array<string, mixed> $credentials */
    public static function hasCredentials(array $credentials): bool
    {
        return filled($credentials['access_token'] ?? null) || filled($credentials['api_token'] ?? null);
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.bitbucket.org/2.0';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => filled($this->credentials['api_token'] ?? null)
                ? 'Basic '.base64_encode($this->credentials['email'].':'.$this->credentials['api_token'])
                : 'Bearer '.$this->credentials['access_token'],
            'Accept' => 'application/json',
        ];
    }
}
