<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Tests\Concerns\LoginUser;
use Timatic\Bitbucket\Filament\Pages\SettingsPage;
use Timatic\Bitbucket\Requests\GetWorkspacesRequest;
use Timatic\Bitbucket\Requests\RefreshTokenRequest;

uses(LoginUser::class);
uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('keeps the webhook and asks to reconnect when bitbucket refuses the refresh token while deleting the webhook', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'workspace' => 'acme',
        'webhook_uuid' => '{uuid}',
        'webhook_secret' => 'a-webhook-secret',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'unauthorized_client', 'error_description' => 'refresh_token is invalid'], 403),
    ]);

    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('delete_webhook')
        ->assertRedirect(SettingsPage::getUrl(['record' => $integration]));

    expect(session('bitbucket_error'))->toBe(__('bitbucket::bitbucket.common.connection_expired_title'));
    expect($integration->refresh()->config)->toBe([
        'workspace' => 'acme',
        'webhook_uuid' => '{uuid}',
        'webhook_secret' => 'a-webhook-secret',
    ]);
});

it('connects with an api token once bitbucket accepts it', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    $mockClient = MockClient::global([
        GetWorkspacesRequest::class => MockResponse::make(['values' => [['administrator' => true, 'workspace' => ['slug' => 'acme']]]]),
    ]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('connectWithToken', ['email' => 'jane@acme.test', 'api_token' => 'secret-token'])
        ->assertHasNoFormErrors();

    // Then
    expect($integration->refresh()->config)->toBe([
        'email' => 'jane@acme.test',
        'api_token' => 'secret-token',
    ]);
    $mockClient->assertSent(fn (Request $request, Response $response): bool => $request instanceof GetWorkspacesRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Basic '.base64_encode('jane@acme.test:secret-token'));
});

it('stores nothing when bitbucket rejects the api token', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => []]);
    MockClient::global([
        GetWorkspacesRequest::class => MockResponse::make(['type' => 'error', 'error' => ['message' => 'Unauthorized']], 401),
    ]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('connectWithToken', ['email' => 'jane@acme.test', 'api_token' => 'wrong-token'])
        ->assertNotified(__('bitbucket::bitbucket.settings.notification_token_rejected'));

    // Then
    expect($integration->refresh()->config)->toBe([]);
});

it('connects without webhook when the account is no admin of the chosen workspace', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Bitbucket', 'type' => 'bitbucket', 'config' => [
        'email' => 'jane@acme.test',
        'api_token' => 'secret-token',
        'webhook_secret' => 'a-webhook-secret',
    ]]);
    MockClient::global([
        GetWorkspacesRequest::class => MockResponse::make(['values' => [['administrator' => false, 'workspace' => ['slug' => 'acme']]]]),
    ]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('choose_workspace', ['workspace' => 'acme']);

    // Then
    expect($integration->refresh()->config)->toBe([
        'email' => 'jane@acme.test',
        'api_token' => 'secret-token',
        'webhook_secret' => 'a-webhook-secret',
        'workspace' => 'acme',
        'workspace_admin' => false,
    ]);
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->assertSee(__('bitbucket::bitbucket.settings.callout_without_webhook_title'))
        ->assertActionHidden('install_webhook');
});
