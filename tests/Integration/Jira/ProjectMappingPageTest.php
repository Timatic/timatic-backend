<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Requests\RefreshTokenRequest;

uses(LoginUser::class);
uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('reports an expired connection when jira refuses the refresh token', function () {
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => [
        'access_token' => 'an-access-token',
        'refresh_token' => 'a-revoked-refresh-token',
        'expires_at' => now()->subMinute()->timestamp,
        'cloud_id' => 'cloud-1',
        'cloud_url' => 'https://acme.atlassian.net',
    ]]);
    MockClient::global([
        RefreshTokenRequest::class => MockResponse::make(['error' => 'unauthorized_client', 'error_description' => 'refresh_token is invalid'], 403),
    ]);

    Livewire::test(ProjectMappingPage::class, ['record' => $integration->id])
        ->assertSuccessful()
        ->assertNotified(__('jira::jira.common.connection_expired_title'));

    expect($integration->refresh()->config)->toBe([]);
});
