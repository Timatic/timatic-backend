<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\GitHub\Filament\Pages\SettingsPage;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

uses(LoginUser::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('shows the not configured callout when the app credentials are missing', function () {
    config()->set('github.client_id', null);
    config()->set('github.app_id', null);
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertSee(__('github::github.settings.callout_not_configured_title'));
});

it('offers the connect action when no user token is stored', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => []]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertSee(__('github::github.settings.callout_disconnected_title'))
        ->assertActionVisible('connect')
        ->assertActionHidden('choose_installation');
});

it('stores the chosen installation', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
    ]]);
    MockClient::global([
        GetUserInstallationsRequest::class => MockResponse::make([
            'total_count' => 1,
            'installations' => [['id' => 4242, 'account' => ['login' => 'acme', 'type' => 'Organization']]],
        ]),
    ]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertActionVisible('choose_installation')
        ->callAction('choose_installation', ['installation_id' => 4242]);

    expect($integration->refresh()->config['installation_id'])->toBe(4242)
        ->and($integration->config['installation_account'])->toBe('acme (Organization)');
});

it('shows the proxy routing line once an installation is linked', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    config()->set('timatic.tenant_slug', 'klant1');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
        'installation_id' => 4242,
        'installation_account' => 'acme',
    ]]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertSee('4242:klant1:'.$integration->id);
});

it('clears the connection on disconnect', function () {
    config()->set('github.client_id', 'Iv1.client');
    config()->set('github.client_secret', 'client-secret');
    config()->set('github.app_id', '123456');
    config()->set('github.private_key', 'private-key');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['name' => 'GitHub', 'type' => 'github', 'config' => [
        'access_token' => 'ghu_user_token',
        'expires_at' => null,
        'installation_id' => 4242,
    ]]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('disconnect');

    expect($integration->refresh()->config)->toBe([]);
});
