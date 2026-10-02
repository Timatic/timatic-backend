<?php

namespace Timatic\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\Requests\RefreshTokenRequest;
use Timatic\GoogleCalendar\Requests\RevokeTokenRequest;

class OAuthService
{
    public function refreshIfExpired(GoogleCalendarConnection $connection): GoogleCalendarConnection
    {
        if (! $connection->hasExpired()) {
            return $connection;
        }

        $lock = Cache::lock('google_calendar.token.refresh.'.$connection->user_id, 10);

        return $lock->block(10, function () use ($connection): GoogleCalendarConnection {
            $connection->refresh();

            if (! $connection->hasExpired()) {
                return $connection;
            }

            return $this->refreshTokens($connection);
        });
    }

    /**
     * Revokes the grant at Google before forgetting it. Google only hands out a refresh token on a
     * first authorization, so a grant left standing would leave the user unable to reconnect.
     */
    public function disconnect(GoogleCalendarConnection $connection): void
    {
        try {
            new OAuthConnector()->send(new RevokeTokenRequest((string) $connection->refresh_token));
        } catch (FatalRequestException|RequestException) {
            // Unreachable or already gone at Google; forgetting it here is the whole point.
        }

        $connection->delete();
    }

    /**
     * A refusal means Google no longer honours the grant, which the user can only answer by
     * connecting again, so the connection is dropped rather than kept in a state that cannot work.
     */
    private function refreshTokens(GoogleCalendarConnection $connection): GoogleCalendarConnection
    {
        $response = new OAuthConnector()->send(
            new RefreshTokenRequest((string) $connection->refresh_token)
        );

        if ($response->status() === 400) {
            $connection->delete();

            return $connection;
        }

        if ($response->failed()) {
            throw new RuntimeException('Google token refresh failed.');
        }

        $tokens = $response->dto();

        $connection->update([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken ?? $connection->refresh_token,
            'expires_at' => $tokens->expiresAt(),
        ]);

        return $connection;
    }
}
