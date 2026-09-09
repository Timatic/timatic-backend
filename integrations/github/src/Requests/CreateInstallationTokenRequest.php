<?php

namespace Timatic\GitHub\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubInstallationToken;

class CreateInstallationTokenRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly int $installationId) {}

    public function resolveEndpoint(): string
    {
        return '/app/installations/'.$this->installationId.'/access_tokens';
    }

    public function createDtoFromResponse(Response $response): GitHubInstallationToken
    {
        return new GitHubInstallationToken(
            token: (string) $response->json('token'),
            expiresAt: CarbonImmutable::parse($response->json('expires_at')),
        );
    }
}
