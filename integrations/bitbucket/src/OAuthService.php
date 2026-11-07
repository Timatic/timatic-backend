<?php

namespace Timatic\Bitbucket;

use App\Models\Integration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Timatic\Bitbucket\DataTransferObjects\BitbucketTokens;
use Timatic\Bitbucket\Requests\ExchangeCodeRequest;
use Timatic\Bitbucket\Requests\RefreshTokenRequest;

class OAuthService
{
    public function buildAuthorizationUrl(Integration $integration): string
    {
        $nonce = Str::random(32);

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['oauth_nonce' => $nonce]),
        ]);

        return 'https://bitbucket.org/site/oauth2/authorize?'.http_build_query([
            'client_id' => config('bitbucket.client_id'),
            'response_type' => 'code',
            'scope' => 'repository account webhook',
            'redirect_uri' => $this->redirectUri(),
            'state' => $this->buildStateJwt($integration->id, $nonce),
        ]);
    }

    public function handleCallback(string $code, string $state): Integration
    {
        $payload = $this->decodeStateJwt($state);
        $integration = Integration::findOrFail((int) $payload['integration_id']);

        if ($payload['nonce'] !== ($integration->config['oauth_nonce'] ?? null)) {
            throw new RuntimeException('Invalid OAuth state parameter.');
        }

        $tokenResponse = new OAuthConnector()->send(new ExchangeCodeRequest($code, $this->redirectUri()));

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Token exchange failed.');
        }

        /** @var BitbucketTokens $tokens */
        $tokens = $tokenResponse->dto();

        $integration->update([
            'config' => array_merge(
                Arr::except($integration->config ?? [], ['oauth_nonce']),
                [
                    'access_token' => $tokens->accessToken,
                    'refresh_token' => $tokens->refreshToken,
                    'expires_at' => now()->addSeconds($tokens->expiresIn - 60)->timestamp,
                ]
            ),
        ]);

        return $integration->refresh();
    }

    public function refreshIfExpired(Integration $integration): Integration
    {
        if (now()->timestamp < ($integration->config['expires_at'] ?? 0)) {
            return $integration;
        }

        $lock = Cache::lock('bitbucket.token.refresh.'.$integration->id, 10);

        return $lock->block(10, function () use ($integration): Integration {
            $integration->refresh();

            if (now()->timestamp < ($integration->config['expires_at'] ?? 0)) {
                return $integration;
            }

            return $this->refreshTokens($integration);
        });
    }

    public function disconnect(Integration $integration): void
    {
        $integration->update([
            'config' => Arr::except($integration->config ?? [], [
                'access_token', 'refresh_token', 'expires_at', 'workspace', 'oauth_nonce',
                'webhook_uuid', 'webhook_uuids', 'webhook_secret',
            ]),
        ]);
    }

    private function refreshTokens(Integration $integration): Integration
    {
        $config = $integration->config ?? [];

        $response = new OAuthConnector()->send(new RefreshTokenRequest($config['refresh_token'] ?? ''));

        if ($response->status() === 400) {
            $this->disconnect($integration);

            return $integration->refresh();
        }

        if ($response->failed()) {
            throw new RuntimeException('Token refresh failed.');
        }

        /** @var BitbucketTokens $tokens */
        $tokens = $response->dto();

        $integration->update([
            'config' => array_merge($config, [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken ?? $config['refresh_token'],
                'expires_at' => now()->addSeconds($tokens->expiresIn - 60)->timestamp,
            ]),
        ]);

        return $integration->refresh();
    }

    private function buildStateJwt(int $integrationId, string $nonce): string
    {
        return JWT::encode([
            'tenant' => config('timatic.tenant_slug'),
            'integration_id' => $integrationId,
            'nonce' => $nonce,
        ], $this->jwtKey(), 'HS256');
    }

    /** @return array<string, mixed> */
    private function decodeStateJwt(string $state): array
    {
        try {
            return (array) JWT::decode($state, new Key($this->jwtKey(), 'HS256'));
        } catch (\Throwable $e) {
            throw new RuntimeException('Invalid state: '.$e->getMessage());
        }
    }

    private function jwtKey(): string
    {
        $key = config('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }

    private function redirectUri(): string
    {
        return rtrim(config('app.auth_proxy_url'), '/').'/integrations/bitbucket/callback';
    }
}
