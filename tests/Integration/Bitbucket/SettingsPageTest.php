<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\Bitbucket\Filament\Pages\SettingsPage;
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
