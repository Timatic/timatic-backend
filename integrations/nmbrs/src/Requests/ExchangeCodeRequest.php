<?php

declare(strict_types=1);

namespace Timatic\Nmbrs\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Timatic\Nmbrs\DataTransferObjects\NmbrsTokens;

class ExchangeCodeRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $code, private readonly string $redirectUri) {}

    public function resolveEndpoint(): string
    {
        return '/connect/token';
    }

    public function createDtoFromResponse(Response $response): NmbrsTokens
    {
        return new NmbrsTokens(
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
            'grant_type' => 'authorization_code',
            'client_id' => config('nmbrs.client_id'),
            'client_secret' => config('nmbrs.client_secret'),
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }
}
