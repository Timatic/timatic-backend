<?php

use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\OAuthService;
use Timatic\GitHub\Requests\GetAuthenticatedUserRequest;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('builds an authorization url without scopes', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('app.auth_proxy_url', 'https://auth.timatic.app');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);

    $url = app(OAuthService::class)->buildAuthorizationUrl($integration);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($url)->toStartWith('https://github.com/login/oauth/authorize?')
        ->and($query['client_id'])->toBe('Iv1.client')
        ->and($query['redirect_uri'])->toBe('https://auth.timatic.app/integrations/github/callback')
        ->and($query)->not->toHaveKey('scope')
        ->and($integration->refresh()->config['oauth_nonce'])->toBeString();
});

it('stores the tokens and the connecting github login', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $state = app(OAuthService::class)->buildAuthorizationUrl($integration);
    parse_str(parse_url($state, PHP_URL_QUERY), $query);
    Http::fake(['github.com/login/oauth/access_token' => Http::response([
        'access_token' => 'ghu_user_token',
        'refresh_token' => 'ghr_refresh_token',
        'expires_in' => 28800,
    ])]);
    MockClient::global([
        GetAuthenticatedUserRequest::class => MockResponse::make(['id' => 99, 'login' => 'octocat']),
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $integration = app(OAuthService::class)->handleCallback('the-code', $query['state']);

    expect($integration->config['access_token'])->toBe('ghu_user_token')
        ->and($integration->config['refresh_token'])->toBe('ghr_refresh_token')
        ->and($integration->config['expires_at'])->toBe(now()->addSeconds(28740)->timestamp)
        ->and($integration->config['github_login'])->toBe('octocat')
        ->and($integration->config['github_user_id'])->toBe(99)
        ->and($integration->config['installations'])->toBe([['id' => 4242, 'account' => 'acme']])
        ->and($integration->config)->not->toHaveKey('oauth_nonce');
});

it('stores a non-expiring token without an expiry', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    parse_str(parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    Http::fake(['github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_user_token'])]);
    MockClient::global([
        GetAuthenticatedUserRequest::class => MockResponse::make(['id' => 99, 'login' => 'octocat']),
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $integration = app(OAuthService::class)->handleCallback('the-code', $query['state']);

    expect($integration->config['expires_at'])->toBeNull()
        ->and($integration->config['refresh_token'])->toBeNull();
});

it('rejects a callback whose nonce does not match', function () {
    config()->set('github.client_id', 'Iv1.client');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    parse_str(parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    $integration->update(['config' => ['oauth_nonce' => 'another-nonce']]);

    app(OAuthService::class)->handleCallback('the-code', $query['state']);
})->throws(GitHubException::class, 'Invalid OAuth state parameter.');

it('fails when github answers the token request with an error', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    parse_str(parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    Http::fake(['github.com/login/oauth/access_token' => Http::response(['error' => 'bad_verification_code'])]);

    app(OAuthService::class)->handleCallback('the-code', $query['state']);
})->throws(GitHubException::class, 'GitHub token request failed: bad_verification_code');

it('refreshes an expired user token', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_old',
        'refresh_token' => 'ghr_old',
        'expires_at' => now()->subMinute()->timestamp,
    ]]);
    Http::fake(['github.com/login/oauth/access_token' => Http::response([
        'access_token' => 'ghu_new',
        'refresh_token' => 'ghr_new',
        'expires_in' => 28800,
    ])]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config['access_token'])->toBe('ghu_new')
        ->and($integration->config['refresh_token'])->toBe('ghr_new');
});

it('leaves a token without expiry untouched', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    Http::fake();

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config['access_token'])->toBe('ghu_user_token');
    Http::assertNothingSent();
});

it('disconnects when the refresh token is rejected', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_old',
        'refresh_token' => 'ghr_old',
        'expires_at' => now()->subMinute()->timestamp,
        'installations' => [['id' => 4242, 'account' => 'acme']],
    ]]);
    Http::fake(['github.com/login/oauth/access_token' => Http::response(['error' => 'bad_refresh_token'])]);

    $integration = app(OAuthService::class)->refreshIfExpired($integration);

    expect($integration->config)->toBe([]);
});

it('clears every oauth key on disconnect', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'refresh_token' => 'ghr_refresh_token',
        'expires_at' => now()->addHour()->timestamp,
        'installations' => [['id' => 4242, 'account' => 'acme']],
        'github_login' => 'octocat',
        'github_user_id' => 99,
    ]]);

    app(OAuthService::class)->disconnect($integration);

    expect($integration->refresh()->config)->toBe([]);
});
