<?php

namespace Timatic\Jira;

use App\Models\Integration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OAuthService
{
    public function buildAuthorizationUrl(Integration $integration): string
    {
        $nonce = Str::random(32);

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['oauth_nonce' => $nonce]),
        ]);

        return 'https://auth.atlassian.com/authorize?'.http_build_query([
            'audience' => 'api.atlassian.com',
            'client_id' => config('jira.client_id'),
            'scope' => 'read:jira-work read:jira-user offline_access',
            'redirect_uri' => $this->redirectUri(),
            'state' => $this->buildStateJwt($integration->id, $nonce),
            'response_type' => 'code',
            'prompt' => 'consent',
        ]);
    }

    public function handleCallback(string $code, string $state): Integration
    {
        $payload = $this->decodeStateJwt($state);
        $integration = Integration::findOrFail((int) $payload['integration_id']);

        if ($payload['nonce'] !== ($integration->config['oauth_nonce'] ?? null)) {
            throw new RuntimeException('Invalid OAuth state parameter.');
        }

        $tokenResponse = Http::post('https://auth.atlassian.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('jira.client_id'),
            'client_secret' => config('jira.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Token exchange failed.');
        }

        $tokens = $tokenResponse->json();

        $resourcesResponse = Http::withToken($tokens['access_token'])
            ->get('https://api.atlassian.com/oauth/token/accessible-resources');

        if ($resourcesResponse->failed() || empty($resourcesResponse->json())) {
            throw new RuntimeException('Could not fetch Jira accessible resources.');
        }

        $resources = $resourcesResponse->json();
        $baseConfig = array_merge(
            Arr::except($integration->config ?? [], ['oauth_nonce']),
            [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($tokens['expires_in'] - 60)->timestamp,
            ]
        );

        if (count($resources) === 1) {
            $integration->update([
                'config' => array_merge($baseConfig, [
                    'cloud_id' => $resources[0]['id'],
                    'cloud_url' => $resources[0]['url'],
                ]),
            ]);
        } else {
            $integration->update([
                'config' => array_merge($baseConfig, [
                    'pending_resources' => $resources,
                ]),
            ]);
        }

        return $integration->refresh();
    }

    public function refreshIfExpired(Integration $integration): Integration
    {
        if (now()->timestamp < ($integration->config['expires_at'] ?? 0)) {
            return $integration;
        }

        $lock = Cache::lock('jira.token.refresh.'.$integration->id, 10);

        return $lock->block(10, function () use ($integration): Integration {
            $integration->refresh();

            if (now()->timestamp < ($integration->config['expires_at'] ?? 0)) {
                return $integration;
            }

            return $this->refreshTokens($integration);
        });
    }

    public function confirmResource(Integration $integration, string $cloudId): Integration
    {
        $config = $integration->config ?? [];
        $rawResources = $config['pending_resources'] ?? [];
        $resources = is_array($rawResources) ? $rawResources : [];

        $resource = collect($resources)->firstWhere('id', $cloudId);

        if (! $resource) {
            throw new RuntimeException('Invalid cloud ID selection.');
        }

        $integration->update([
            'config' => array_merge(
                Arr::except($config, ['pending_resources']),
                [
                    'cloud_id' => $resource['id'],
                    'cloud_url' => $resource['url'],
                ]
            ),
        ]);

        return $integration->refresh();
    }

    public function disconnect(Integration $integration): void
    {
        $integration->update([
            'config' => Arr::except($integration->config ?? [], [
                'access_token', 'refresh_token', 'expires_at', 'cloud_id', 'cloud_url', 'oauth_nonce', 'pending_resources',
            ]),
        ]);
    }

    private function refreshTokens(Integration $integration): Integration
    {
        $config = $integration->config ?? [];

        $response = Http::post('https://auth.atlassian.com/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => config('jira.client_id'),
            'client_secret' => config('jira.client_secret'),
            'refresh_token' => $config['refresh_token'] ?? '',
        ]);

        if ($response->status() === 400) {
            $this->disconnect($integration);

            return $integration->refresh();
        }

        if ($response->failed()) {
            throw new RuntimeException('Token refresh failed.');
        }

        $tokens = $response->json();

        $integration->update([
            'config' => array_merge($config, [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $config['refresh_token'],
                'expires_at' => now()->addSeconds($tokens['expires_in'] - 60)->timestamp,
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
        return rtrim(config('app.auth_proxy_url'), '/').'/integrations/jira/callback';
    }
}
