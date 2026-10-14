<?php

namespace Timatic\GoogleCalendar\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasFormBody;

/**
 * Hands the grant back to Google. Revoking the refresh token drops the whole grant, so the next
 * login is a first authorization again and returns a refresh token without Timatic having to ask
 * for a consent screen on every login.
 */
class RevokeTokenRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $refreshToken) {}

    public function resolveEndpoint(): string
    {
        return '/revoke';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['token' => $this->refreshToken];
    }
}
