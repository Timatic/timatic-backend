<?php

namespace Timatic\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Timatic\GoogleCalendar\Requests\RefreshTokenRequest;

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

    public function disconnect(User $user): void
    {
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
