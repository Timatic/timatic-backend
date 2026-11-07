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

it('disconnects when bitbucket refuses the refresh token', function () {
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'workspace' => 'acme',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config)->toBe([]);
});
