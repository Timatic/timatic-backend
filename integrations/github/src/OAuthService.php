<?php

namespace Timatic\GitHub;

use App\Models\Integration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Saloon\Http\Request;
use Timatic\GitHub\DataTransferObjects\GitHubTokens;
use Timatic\GitHub\DataTransferObjects\GitHubUser;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\Requests\ExchangeCodeRequest;
use Timatic\GitHub\Requests\GetAuthenticatedUserRequest;
use Timatic\GitHub\Requests\RefreshTokenRequest;

class OAuthService
{
    private const AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';

    /**
     * A GitHub App has no scopes: the fine-grained permissions of the app itself apply.
     */
    public function buildAuthorizationUrl(Integration $integration): string
    {
        $nonce = Str::random(32);

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['oauth_nonce' => $nonce]),
        ]);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('github.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $this->buildStateJwt($integration->id, $nonce),
        ]);
    }

    public function handleCallback(string $code, string $state): Integration
    {
        $payload = $this->decodeStateJwt($state);
        $integration = Integration::findOrFail((int) $payload['integration_id']);

        if ($payload['nonce'] !== ($integration->config['oauth_nonce'] ?? null)) {
            throw new GitHubException('Invalid OAuth state parameter.');
        }

        $tokens = $this->requestTokens(new ExchangeCodeRequest($code, $this->redirectUri()));

        $integration->update([
            'config' => array_merge(
                Arr::except($integration->config ?? [], ['oauth_nonce']),
                $this->tokenConfig($tokens),
            ),
        ]);

        return $this->adoptInstallations($this->storeConnectingUser($integration->refresh()));
    }

    public function refreshIfExpired(Integration $integration): Integration
    {
        $expiresAt = $integration->config['expires_at'] ?? null;

        if ($expiresAt === null || now()->timestamp < $expiresAt) {
            return $integration;
        }

        $lock = Cache::lock('github.token.refresh.'.$integration->id, 10);

        return $lock->block(10, function () use ($integration): Integration {
            $integration->refresh();
            $expiresAt = $integration->config['expires_at'] ?? null;

            if ($expiresAt === null || now()->timestamp < $expiresAt) {
                return $integration;
            }

            return $this->refreshTokens($integration);
        });
    }

    public function disconnect(Integration $integration): void
    {
        $integration->update([
            'config' => Arr::except($integration->config ?? [], [
                'access_token',
                'refresh_token',
                'expires_at',
                'oauth_nonce',
                'installations',
                'delegate_return_token',
                'github_login',
                'github_user_id',
            ]),
        ]);
    }

    public function installUrl(): string
    {
        return 'https://github.com/apps/'.config('github.app_slug').'/installations/new';
    }

    private function refreshTokens(Integration $integration): Integration
    {
        $config = $integration->config ?? [];

        try {
            $tokens = $this->requestTokens(new RefreshTokenRequest($config['refresh_token'] ?? ''));
        } catch (GitHubException) {
            $this->disconnect($integration);

            return $integration->refresh();
        }

        $integration->update([
            'config' => array_merge($config, $this->tokenConfig($tokens, $config)),
        ]);

        return $integration->refresh();
    }

    /**
     * GitHub answers a failed token request with HTTP 200 and an error key.
     */
    private function requestTokens(Request $request): GitHubTokens
    {
        $response = new OAuthConnector()->send($request);

        $error = $response->failed() ? 'http_'.$response->status() : $response->json('error');

        if ($error === null) {
            return $response->dto();
        }

        throw new GitHubException('GitHub token request failed: '.$error);
    }

    /**
     * Expiring user tokens are optional per app: without them GitHub returns
     * neither an expiry nor a refresh token, and the access token stays valid.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function tokenConfig(GitHubTokens $tokens, array $config = []): array
    {
        return [
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken ?? ($config['refresh_token'] ?? null),
            'expires_at' => $tokens->expiresIn === null ? null : now()->addSeconds($tokens->expiresIn - 60)->timestamp,
        ];
    }

    /**
     * A failed lookup leaves the list empty: the settings page can refresh it, and
     * the connection itself is already usable.
     */
    private function adoptInstallations(Integration $integration): Integration
    {
        try {
            app(InstallationService::class)->refresh($integration);
        } catch (GitHubException $e) {
            return $integration;
        }

        return $integration->refresh();
    }

    private function storeConnectingUser(Integration $integration): Integration
    {
        /** @var GitHubUser $user */
        $user = Connector::forUser($integration->config['access_token'])
            ->send(new GetAuthenticatedUserRequest)
            ->dto();

        $integration->update([
            'config' => array_merge($integration->config ?? [], [
                'github_login' => $user->login,
                'github_user_id' => $user->id,
            ]),
        ]);

        return $integration->refresh();
    }

    private function buildStateJwt(int $integrationId, string $nonce): string
    {
        return JWT::encode([
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
            throw new GitHubException('Invalid state: '.$e->getMessage());
        }
    }

    private function jwtKey(): string
    {
        $key = config('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }

    /**
     * Tenants share one GitHub App whose callback URL has wildcard matching enabled,
     * so every tenant subdomain can receive the callback directly.
     */
    private function redirectUri(): string
    {
        return route('github.oauth.callback');
    }
}
