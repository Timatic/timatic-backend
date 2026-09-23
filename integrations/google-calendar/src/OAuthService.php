<?php

namespace Timatic\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Timatic\GoogleCalendar\Requests\RefreshTokenRequest;
use Timatic\GoogleCalendar\Requests\RevokeTokenRequest;

class OAuthService
{
    public function refreshIfExpired(User $user): User
    {
        if (now()->timestamp < $user->oauth_token_expires_at) {
            return $user;
        }

        $lock = Cache::lock('google_calendar.token.refresh.'.$user->id, 10);

        return $lock->block(10, function () use ($user): User {
            $user->refresh();

            if (now()->timestamp < $user->oauth_token_expires_at) {
                return $user;
            }

            return $this->refreshTokens($user);
        });
    }

    /**
     * Revokes the grant at Google before forgetting the tokens. Google only hands out a refresh
     * token on a first authorization, so a grant left standing would leave the user unable to
     * reconnect by logging in again.
     */
    public function disconnect(User $user): void
    {
        if (filled($user->oauth_refresh_token)) {
            try {
                new OAuthConnector()->send(new RevokeTokenRequest((string) $user->oauth_refresh_token));
            } catch (FatalRequestException|RequestException) {
                // Unreachable or already gone at Google; forgetting it here is the whole point.
            }
        }

        $user->update([
            'oauth_access_token' => null,
            'oauth_refresh_token' => null,
            'oauth_token_expires_at' => 0,
        ]);
    }

    private function refreshTokens(User $user): User
    {
        $response = new OAuthConnector()->send(
            new RefreshTokenRequest((string) $user->oauth_refresh_token)
        );

        if ($response->status() === 400) {
            $user->update([
                'oauth_access_token' => null,
                'oauth_refresh_token' => null,
                'oauth_token_expires_at' => 0,
            ]);

            return $user->refresh();
        }

        if ($response->failed()) {
            throw new RuntimeException('Google token refresh failed.');
        }

        $tokens = $response->dto();

        $user->update(array_filter([
            'oauth_access_token' => $tokens->accessToken,
            'oauth_refresh_token' => $tokens->refreshToken ?? $user->oauth_refresh_token,
            'oauth_token_expires_at' => now()->addSeconds($tokens->expiresIn - 60)->timestamp,
        ]));

        return $user->refresh();
    }
}
