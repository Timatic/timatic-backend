<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Jira\OAuthService;
use Timatic\Jira\Requests\ExchangeCodeRequest;
use Timatic\Jira\Requests\GetAccessibleResourcesRequest;
use Timatic\Jira\Requests\RefreshTokenRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('stores the tokens and the only accessible jira site', function () {
    $this->freezeTime();
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    parse_str((string) parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    MockClient::global([
        ExchangeCodeRequest::class => MockResponse::make(['access_token' => 'an-access-token', 'refresh_token' => 'a-refresh-token', 'expires_in' => 3600]),
        GetAccessibleResourcesRequest::class => MockResponse::make([['id' => 'cloud-1', 'url' => 'https://acme.atlassian.net', 'name' => 'Acme']]),
    ]);

    $integration = app(OAuthService::class)->handleCallback('the-code', $query['state']);

    expect($integration->config)->toBe([
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-refresh-token',
        'expires_at' => now()->addSeconds(3540)->timestamp,
        'cloud_id' => 'cloud-1',
        'cloud_url' => 'https://acme.atlassian.net',
    ]);
});

it('disconnects when jira refuses the refresh token', function () {
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'cloud_id' => 'cloud-1',
        'cloud_url' => 'https://acme.atlassian.net',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config)->toBe([]);
});
