<?php

declare(strict_types=1);

namespace Timatic\GitHub\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Timatic\GitHub\DataTransferObjects\GitHubTokens;

class ExchangeCodeRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $code, private readonly string $redirectUri) {}

    public function resolveEndpoint(): string
    {
        return '/login/oauth/access_token';
    }

    public function createDtoFromResponse(Response $response): GitHubTokens
    {
        $expiresIn = $response->json('expires_in');

        return new GitHubTokens(
            accessToken: (string) $response->json('access_token'),
            refreshToken: $response->json('refresh_token'),
            expiresIn: $expiresIn === null ? null : (int) $expiresIn,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'client_id' => config('github.client_id'),
            'client_secret' => config('github.client_secret'),
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }
}
