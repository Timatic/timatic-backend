<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Filament\Pages\SettingsPage;
use Timatic\GitHub\OAuthService;
use Timatic\GitHub\Requests\GetAuthenticatedUserRequest;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('returns an admin to the settings page after connecting', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    Filament::setCurrentPanel('admin');
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

    $this->get(route('github.oauth.callback', ['code' => 'the-code', 'state' => $query['state']]))
        ->assertRedirect(SettingsPage::getUrl(['record' => $integration->id]));

    expect($integration->refresh()->config['access_token'])->toBe('ghu_user_token');
});

it('returns a delegate to the share link page after connecting', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $integration->generateShareToken();
    parse_str(parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);
    $integration->update(['config' => array_merge($integration->config, ['delegate_return_token' => $integration->share_token])]);
    Http::fake(['github.com/login/oauth/access_token' => Http::response(['access_token' => 'ghu_user_token'])]);
    MockClient::global([
        GetAuthenticatedUserRequest::class => MockResponse::make(['id' => 99, 'login' => 'octocat']),
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $this->get(route('github.oauth.callback', ['code' => 'the-code', 'state' => $query['state']]))
        ->assertRedirect(route('github.delegate.show', $integration->share_token).'?connected=1');

    expect($integration->refresh()->config)->not->toHaveKey('delegate_return_token');
});

it('reports a refused authorization on the settings page', function () {
    config()->set('github.client_id', 'Iv1.client');
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    parse_str(parse_url(app(OAuthService::class)->buildAuthorizationUrl($integration), PHP_URL_QUERY), $query);

    $this->get(route('github.oauth.callback', [
        'error' => 'access_denied',
        'error_description' => 'The user denied the request',
        'state' => $query['state'],
    ]))
        ->assertRedirect(SettingsPage::getUrl(['record' => $integration->id]))
        ->assertSessionHas('github_error');

    expect($integration->refresh()->config)->not->toHaveKey('access_token');
});
