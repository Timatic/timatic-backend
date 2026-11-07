<?php

declare(strict_types=1);

namespace Timatic\Jira\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Timatic\Jira\DataTransferObjects\JiraTokens;

class RefreshTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $refreshToken) {}

    public function resolveEndpoint(): string
    {
        return '/oauth/token';
    }

    public function createDtoFromResponse(Response $response): JiraTokens
    {
        return new JiraTokens(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresIn: (int) $response->json('expires_in'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'refresh_token',
            'client_id' => config('jira.client_id'),
            'client_secret' => config('jira.client_secret'),
            'refresh_token' => $this->refreshToken,
        ];
    }
}
