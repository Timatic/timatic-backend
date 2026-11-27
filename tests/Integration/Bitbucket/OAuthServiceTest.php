<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Bitbucket\OAuthService;
use Timatic\Bitbucket\Requests\ExchangeCodeRequest;
use Timatic\Bitbucket\Requests\RefreshTokenRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('stores the tokens bitbucket hands out for the code', function () {
    $this->freezeTime();
    config()->set('bitbucket.client_id', 'a-client-id');
    config()->set('bitbucket.client_secret', 'a-client-secret');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    parse_str((string) parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    $mockClient = MockClient::global([
        ExchangeCodeRequest::class => MockResponse::make(['access_token' => 'an-access-token', 'refresh_token' => 'a-refresh-token', 'expires_in' => 7200]),
    ]);

    $integration = app(OAuthService::class)->handleCallback('the-code', $query['state']);

    expect($integration->config)->toBe([
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-refresh-token',
        'expires_at' => now()->addSeconds(7140)->timestamp,
    ]);
    $mockClient->assertSent(fn (ExchangeCodeRequest $request, $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === 'Basic '.base64_encode('a-client-id:a-client-secret'));
});

it('drops only the tokens when bitbucket refuses the refresh token', function (int $status, array $body) {
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'workspace' => 'acme',
        'webhook_uuid' => '{uuid}',
        'webhook_secret' => 'a-webhook-secret',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make($body, $status),
    ]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config)->toBe([
        'workspace' => 'acme',
        'webhook_uuid' => '{uuid}',
        'webhook_secret' => 'a-webhook-secret',
    ]);
})->with([
    'unauthorized client' => [403, ['error' => 'unauthorized_client', 'error_description' => 'refresh_token is invalid']],
    'invalid grant' => [400, ['error' => 'invalid_grant']],
]);

it('does not ask bitbucket for new tokens without a refresh token', function () {
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'access_token' => 'an-access-token',
        'expires_at' => now()->subMinute()->timestamp,
        'workspace' => 'acme',
    ]]);
    $mockClient = MockClient::global();

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    $mockClient->assertNothingSent();
    expect($integration->config['workspace'])->toBe('acme');
});

it('reports the status and body when bitbucket fails the refresh for another reason', function () {
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'workspace' => 'acme',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'unauthorized_client'], 401),
    ]);

    expect(fn () => app(OAuthService::class)->refreshIfExpired($integration))
        ->toThrow(RuntimeException::class, 'Token refresh failed with status 401: {"error":"unauthorized_client"}');
    expect($integration->refresh()->config['workspace'])->toBe('acme');
});
