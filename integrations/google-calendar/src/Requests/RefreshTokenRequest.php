<?php

namespace Timatic\GoogleCalendar\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use Timatic\GoogleCalendar\DataTransferObjects\RefreshedTokens;

class RefreshTokenRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $refreshToken) {}

    public function resolveEndpoint(): string
    {
        return '/token';
    }

    public function createDtoFromResponse(Response $response): RefreshedTokens
    {
        return new RefreshedTokens(
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
            'client_id' => config('google_calendar.client_id'),
            'client_secret' => config('google_calendar.client_secret'),
            'refresh_token' => $this->refreshToken,
        ];
    }
}
