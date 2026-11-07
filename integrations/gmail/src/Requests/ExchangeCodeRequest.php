<?php

declare(strict_types=1);

namespace Timatic\Gmail\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Timatic\Gmail\DataTransferObjects\GoogleTokens;

class ExchangeCodeRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $code) {}

    public function resolveEndpoint(): string
    {
        return '/token';
    }

    public function createDtoFromResponse(Response $response): GoogleTokens
    {
        return new GoogleTokens(
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
            'client_id' => config('gmail.client_id'),
            'client_secret' => config('gmail.client_secret'),
            'redirect_uri' => config('gmail.redirect'),
            'code' => $this->code,
        ];
    }
}
