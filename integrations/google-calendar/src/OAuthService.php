<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Timatic\GoogleCalendar\Exceptions\CalendarConnectionFailedException;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;
use Timatic\GoogleCalendar\Requests\ExchangeCodeRequest;
use Timatic\GoogleCalendar\Requests\RefreshTokenRequest;
use Timatic\GoogleCalendar\Requests\RevokeTokenRequest;

class OAuthService
{
    /**
     * Ties the code Google sends back to the user who asked for it, so a code from anywhere else is
     * refused. The cache holds it because the flow starts from a signed url rather than a session.
     */
    public const STATE_CACHE_PREFIX = 'google_calendar.state.';

    /**
     * Calendar access is asked for here rather than at login, so a user who does not want their
     * calendar read never sees the consent screen. Consent is forced because Google hands out a
     * refresh token on a first authorization only, and the sync cannot run without one.
     */
    public function buildAuthorizationUrl(User $user): string
    {
        $nonce = Str::random(32);

        Cache::put(self::STATE_CACHE_PREFIX.$nonce, $user->id, now()->addMinutes(10));

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('google_calendar.client_id'),
            'redirect_uri' => config('google_calendar.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $nonce,
        ]);
    }

    /**
     * @throws CalendarConnectionFailedException
     */
    public function handleCallback(string $code, string $state): GoogleCalendarConnection
    {
        $user = User::find((int) Cache::pull(self::STATE_CACHE_PREFIX.$state))
            ?? throw CalendarConnectionFailedException::unknownState();

        $response = new OAuthConnector()->send(new ExchangeCodeRequest($code));

        if ($response->failed()) {
            throw CalendarConnectionFailedException::codeRefused($response->status());
        }

        $tokens = $response->dto();

        $connection = GoogleCalendarConnection::firstOrNew(['user_id' => $user->id]);

        // Google may leave the refresh token out on a reconnect; the one already held stays valid.
        $connection->fill([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken
                ?? $connection->refresh_token
                ?? throw CalendarConnectionFailedException::missingRefreshToken(),
            'expires_at' => $tokens->expiresAt(),
        ])->save();

        return $connection;
    }

    /**
     * Null means the connection is gone: disconnected while this waited, or refused by Google.
     */
    public function refreshIfExpired(GoogleCalendarConnection $connection): ?GoogleCalendarConnection
    {
        if (! $connection->hasExpired()) {
            return $connection;
        }

        $lock = Cache::lock('google_calendar.token.refresh.'.$connection->user_id, 10);

        return $lock->block(10, function () use ($connection): ?GoogleCalendarConnection {
            $current = $connection->fresh();

            if ($current === null || ! $current->hasExpired()) {
                return $current;
            }

            return $this->refreshTokens($current);
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
    private function refreshTokens(GoogleCalendarConnection $connection): ?GoogleCalendarConnection
    {
        $response = new OAuthConnector()->send(
            new RefreshTokenRequest((string) $connection->refresh_token)
        );

        if ($response->status() === 400) {
            $connection->delete();

            return null;
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
