<?php

namespace Timatic\GitHub;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Timatic\GitHub\DataTransferObjects\GitHubInstallationToken;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\Requests\CreateInstallationTokenRequest;

class InstallationTokenService
{
    private const CACHE_SECONDS = 3300;

    public function token(int $installationId): string
    {
        return Cache::remember(
            'github.installation_token.'.$installationId,
            self::CACHE_SECONDS,
            fn (): string => $this->mintToken($installationId),
        );
    }

    public function appJwt(): string
    {
        $appId = (string) config('github.app_id');

        if ($appId === '') {
            throw new GitHubException('GitHub app id is not configured.');
        }

        try {
            return JWT::encode([
                'iat' => now()->subMinute()->timestamp,
                'exp' => now()->addMinutes(9)->timestamp,
                'iss' => $appId,
            ], $this->privateKey(), 'RS256');
        } catch (Throwable $e) {
            throw new GitHubException('Signing the GitHub app jwt failed: '.$e->getMessage());
        }
    }

    private function mintToken(int $installationId): string
    {
        $response = Connector::forApp()->send(new CreateInstallationTokenRequest($installationId));

        if ($response->failed()) {
            throw new GitHubException(sprintf(
                'Minting an installation token for installation %d failed with status %d.',
                $installationId,
                $response->status(),
            ));
        }

        /** @var GitHubInstallationToken $token */
        $token = $response->dto();

        return $token->token;
    }

    private function privateKey(): string
    {
        $key = str_replace('\n', "\n", (string) config('github.private_key'));

        if (trim($key) === '') {
            throw new GitHubException('GitHub app private key is not configured.');
        }

        return $key;
    }
}
