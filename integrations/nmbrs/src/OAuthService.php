<?php

namespace Timatic\Nmbrs;

use App\Models\Integration;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Timatic\Nmbrs\DataTransferObjects\NmbrsCompany;
use Timatic\Nmbrs\Requests\GetCompaniesRequest;

class OAuthService
{
    private const AUTHORIZE_URL = 'https://identityservice.nmbrs.com/connect/authorize';

    private const TOKEN_URL = 'https://identityservice.nmbrs.com/connect/token';

    private const SCOPES = 'offline_access employee.info.read employee.leave.read employee.payment employee.employment.read company.info.read company.payrollsettings.read';

    public function buildAuthorizationUrl(Integration $integration): string
    {
        $nonce = Str::random(32);

        $integration->update([
            'config' => array_merge($integration->config ?? [], ['oauth_nonce' => $nonce]),
        ]);

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('nmbrs.client_id'),
            'scope' => self::SCOPES,
            'redirect_uri' => $this->redirectUri(),
            'state' => $this->buildStateJwt($integration->id, $nonce),
            'response_type' => 'code',
        ]);
    }

    public function handleCallback(string $code, string $state): Integration
    {
        $payload = $this->decodeStateJwt($state);
        $integration = Integration::findOrFail((int) $payload['integration_id']);

        if ($payload['nonce'] !== ($integration->config['oauth_nonce'] ?? null)) {
            throw new RuntimeException('Invalid OAuth state parameter.');
        }

        $tokenResponse = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => config('nmbrs.client_id'),
            'client_secret' => config('nmbrs.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ]);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Token exchange failed: '.$tokenResponse->body());
        }

        $tokens = $tokenResponse->json();

        $company = $this->fetchFirstCompany($tokens['access_token']);

        $integration->update([
            'config' => array_merge(
                Arr::except($integration->config ?? [], ['oauth_nonce']),
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'expires_at' => now()->addSeconds($tokens['expires_in'] - 60)->timestamp,
                    'company_id' => $company->companyId,
                    'company_name' => $company->name,
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

        $lock = Cache::lock('nmbrs.token.refresh.'.$integration->id, 10);

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
                'access_token', 'refresh_token', 'expires_at', 'company_id', 'company_name', 'oauth_nonce',
            ]),
        ]);
    }

    private function fetchFirstCompany(string $accessToken): NmbrsCompany
    {
        $connector = new Connector($accessToken);
        $response = $connector->send(new GetCompaniesRequest);

        if ($response->failed()) {
            throw new RuntimeException('Could not fetch NMBRS companies.');
        }

        $company = $response->dto()->first();

        if (! $company instanceof NmbrsCompany) {
            throw new RuntimeException('No company found in NMBRS account.');
        }

        return $company;
    }

    private function refreshTokens(Integration $integration): Integration
    {
        $config = $integration->config ?? [];

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => config('nmbrs.client_id'),
            'client_secret' => config('nmbrs.client_secret'),
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
        return rtrim(config('app.auth_proxy_url'), '/').'/integrations/nmbrs/callback';
    }
}
