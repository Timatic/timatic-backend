<?php

use App\Models\Integration;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\SettingsPage;
use Timatic\Jira\Requests\GetMyselfRequest;
use Timatic\Jira\Requests\GetTenantInfoRequest;

uses(LoginUser::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('connects with an api token once jira accepts it', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    $mockClient = MockClient::global([
        GetTenantInfoRequest::class => MockResponse::make(['cloudId' => 'cloud-1']),
        GetMyselfRequest::class => MockResponse::make(['accountId' => 'account-1', 'emailAddress' => 'jane@acme.test', 'displayName' => 'Jane']),
    ]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('connectWithToken', ['site_url' => 'https://acme.atlassian.net/', 'email' => 'jane@acme.test', 'api_token' => 'secret-token'])
        ->assertHasNoFormErrors();

    // Then
    expect($integration->refresh()->config)->toBe([
        'email' => 'jane@acme.test',
        'api_token' => 'secret-token',
        'cloud_id' => 'cloud-1',
        'cloud_url' => 'https://acme.atlassian.net',
    ]);
    $mockClient->assertSent(fn (Request $request, Response $response): bool => $request instanceof GetTenantInfoRequest
        && $response->getPendingRequest()->getUrl() === 'https://acme.atlassian.net/_edge/tenant_info');
    $mockClient->assertSent(fn (Request $request, Response $response): bool => $request instanceof GetMyselfRequest
        && $response->getPendingRequest()->getUrl() === 'https://api.atlassian.com/ex/jira/cloud-1/rest/api/3/myself'
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Basic '.base64_encode('jane@acme.test:secret-token'));
});

it('stores nothing when jira rejects the api token', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $integration = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => []]);
    MockClient::global([
        GetTenantInfoRequest::class => MockResponse::make(['cloudId' => 'cloud-1']),
        GetMyselfRequest::class => MockResponse::make(['message' => 'Client must be authenticated to access this resource.'], 401),
    ]);

    // When
    Livewire::test(SettingsPage::class, ['record' => $integration->id])
        ->callAction('connectWithToken', ['site_url' => 'https://acme.atlassian.net', 'email' => 'jane@acme.test', 'api_token' => 'wrong-token'])
        ->assertNotified(__('jira::jira.settings.notification_token_rejected'));

    // Then
    expect($integration->refresh()->config)->toBe([]);
});
