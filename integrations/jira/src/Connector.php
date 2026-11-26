<?php

namespace Timatic\Jira;

use Psr\Http\Message\RequestInterface;
use Saloon\Http\Connector as SaloonConnector;
use Saloon\Http\PendingRequest;

class Connector extends SaloonConnector
{
    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials) {}

    /** @param array<string, mixed> $credentials */
    public static function hasCredentials(array $credentials): bool
    {
        return filled($credentials['cloud_id'] ?? null)
            && (filled($credentials['access_token'] ?? null) || filled($credentials['api_token'] ?? null));
    }

    public function handlePsrRequest(RequestInterface $request, PendingRequest $pendingRequest): RequestInterface
    {
        $uri = $request->getUri();

        return $request->withUri($uri->withQuery(str_replace('+', '%20', $uri->getQuery())));
    }

    public function resolveBaseUrl(): string
    {
        return 'https://api.atlassian.com/ex/jira/'.$this->credentials['cloud_id'];
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
