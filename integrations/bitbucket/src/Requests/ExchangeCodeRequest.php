<?php

declare(strict_types=1);

namespace Timatic\Bitbucket\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Timatic\Bitbucket\DataTransferObjects\BitbucketTokens;

class ExchangeCodeRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $code, private readonly string $redirectUri) {}

    public function resolveEndpoint(): string
    {
        return '/site/oauth2/access_token';
    }

    public function createDtoFromResponse(Response $response): BitbucketTokens
    {
        return new BitbucketTokens(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresIn: (int) ($response->json('expires_in') ?? 7200),
        );
    }

    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator((string) config('bitbucket.client_id'), (string) config('bitbucket.client_secret'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'authorization_code',
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }
}
