<?php

use App\Models\Integration;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

afterEach(function () {
    MockClient::destroyGlobal();
});

it('offers the github connect button to a delegate without a token', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);
    $integration->generateShareToken();

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee(route('github.delegate.oauth-redirect', $integration->share_token));
});

it('lists the installations of the connected delegate', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('acme (Organization)');
});

it('points the delegate at the app installation page when nothing is installed yet', function () {
    config()->set('github.app_slug', 'timatic');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make(['total_count' => 0, 'installations' => []]),
    ]);

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('https://github.com/apps/timatic/installations/new');
});

it('stores the installation the delegate selects', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $this->post(route('github.delegate.choose-installation', $integration->share_token), ['installation_id' => 4242])
        ->assertRedirect(route('github.delegate.show', $integration->share_token).'?installation_selected=1');

    expect($integration->refresh()->config['installation_id'])->toBe(4242)
        ->and($integration->config['installation_account'])->toBe('acme');
});

it('refuses an installation the delegate has no access to', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    $integration->generateShareToken();
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    $this->post(route('github.delegate.choose-installation', $integration->share_token), ['installation_id' => 9999])
        ->assertRedirect(route('github.delegate.show', $integration->share_token).'?error=installation_invalid');

    expect($integration->refresh()->config)->not->toHaveKey('installation_id');
});

it('tells the delegate the setup is done once an installation is linked', function () {
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
        'installation_id' => 4242,
    ]]);
    $integration->generateShareToken();

    $this->get(route('github.delegate.show', $integration->share_token))
        ->assertOk()
        ->assertSee('installatie is gekoppeld', escape: false);
});
