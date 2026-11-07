<?php

namespace Timatic\Jira;

use App\Models\Integration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Timatic\Jira\DataTransferObjects\JiraCloudResource;
use Timatic\Jira\DataTransferObjects\JiraTokens;
use Timatic\Jira\Requests\ExchangeCodeRequest;
use Timatic\Jira\Requests\GetAccessibleResourcesRequest;
use Timatic\Jira\Requests\RefreshTokenRequest;

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

        $connector = new OAuthConnector;

        $tokenResponse = $connector->send(new ExchangeCodeRequest($code, $this->redirectUri()));

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Token exchange failed.');
        }

        /** @var JiraTokens $tokens */
        $tokens = $tokenResponse->dto();

        $resourcesResponse = $connector->send(new GetAccessibleResourcesRequest($tokens->accessToken));

        if ($resourcesResponse->failed()) {
            throw new RuntimeException('Could not fetch Jira accessible resources.');
        }

        /** @var Collection<int, JiraCloudResource> $resources */
        $resources = $resourcesResponse->dto();

        if ($resources->isEmpty()) {
            throw new RuntimeException('Could not fetch Jira accessible resources.');
        }

        $baseConfig = array_merge(
            Arr::except($integration->config ?? [], ['oauth_nonce']),
            [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'expires_at' => now()->addSeconds($tokens->expiresIn - 60)->timestamp,
            ]
        );

        if ($resources->count() === 1) {
            $integration->update([
                'config' => array_merge($baseConfig, [
                    'cloud_id' => $resources->first()->id,
                    'cloud_url' => $resources->first()->url,
                ]),
            ]);
        } else {
            $integration->update([
                'config' => array_merge($baseConfig, [
                    'pending_resources' => $resources
                        ->map(fn (JiraCloudResource $resource): array => ['id' => $resource->id, 'url' => $resource->url])
                        ->all(),
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

        $response = new OAuthConnector()->send(new RefreshTokenRequest($config['refresh_token'] ?? ''));

        if ($response->status() === 400) {
            $this->disconnect($integration);

            return $integration->refresh();
        }

        if ($response->failed()) {
            throw new RuntimeException('Token refresh failed.');
        }

        /** @var JiraTokens $tokens */
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
        return rtrim(config('app.auth_proxy_url'), '/').'/integrations/jira/callback';
    }
}
