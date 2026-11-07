<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\ExchangeCodeRequest;
use Timatic\Nmbrs\Requests\GetCompaniesRequest;
use Timatic\Nmbrs\Requests\RefreshTokenRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('stores the tokens and the first nmbrs company', function () {
    $this->freezeTime();
    $integration = Integration::create(['title' => 'Nmbrs', 'type' => 'nmbrs', 'config' => []]);
    parse_str((string) parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    MockClient::global([
        ExchangeCodeRequest::class => MockResponse::make(['access_token' => 'an-access-token', 'refresh_token' => 'a-refresh-token', 'expires_in' => 3600]),
        GetCompaniesRequest::class => MockResponse::make(['data' => [['companyId' => 'company-1', 'name' => 'Acme']]]),
    ]);

    $integration = app(OAuthService::class)->handleCallback('the-code', $query['state']);

    expect($integration->config)->toBe([
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-refresh-token',
        'expires_at' => now()->addSeconds(3540)->timestamp,
        'company_id' => 'company-1',
        'company_name' => 'Acme',
    ]);
});

it('disconnects when nmbrs refuses the refresh token', function () {
    $integration = Integration::create(['title' => 'Nmbrs', 'type' => 'nmbrs', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'company_id' => 'company-1',
        'company_name' => 'Acme',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
    ]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config)->toBe([]);
});
