<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidAuthorizationCodeException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ExtensionAuthorizationService
{
    /**
     * @return array<int, string>
     */
    public function allowedRedirectUris(): array
    {
        return array_map(
            fn (string $extensionId): string => 'https://'.$extensionId.'.chromiumapp.org/',
            config('extension.ids', []),
        );
    }

    public function isAllowedRedirectUri(string $redirectUri): bool
    {
        return in_array($redirectUri, $this->allowedRedirectUris(), true);
    }

    public function issueCode(User $user, string $codeChallenge, string $redirectUri): string
    {
        $code = Str::random(64);

        Cache::put(
            $this->cacheKey($code),
            [
                'user_id' => $user->id,
                'code_challenge' => $codeChallenge,
                'redirect_uri' => $redirectUri,
            ],
            now()->addSeconds((int) config('extension.code_lifetime_seconds')),
        );

        return $code;
    }

    /**
     * @throws InvalidAuthorizationCodeException
     */
    public function claimCode(string $code, string $codeVerifier, string $redirectUri): User
    {
        /** @var array{user_id: int, code_challenge: string, redirect_uri: string}|null $payload */
        $payload = Cache::pull($this->cacheKey($code));

        if ($payload === null) {
            throw InvalidAuthorizationCodeException::unknown();
        }

        if ($payload['redirect_uri'] !== $redirectUri) {
            throw InvalidAuthorizationCodeException::redirectUriMismatch();
        }

        if (! hash_equals($payload['code_challenge'], $this->challengeFor($codeVerifier))) {
            throw InvalidAuthorizationCodeException::verifierMismatch();
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user instanceof User) {
            throw InvalidAuthorizationCodeException::unknown();
        }

        return $user;
    }

    private function challengeFor(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    private function cacheKey(string $code): string
    {
        return 'extension-auth-code:'.hash('sha256', $code);
    }
}
