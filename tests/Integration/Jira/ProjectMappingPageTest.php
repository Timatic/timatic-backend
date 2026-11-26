<?php

use App\Models\Customer;
use App\Models\Integration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\ProjectMappingPage;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetProjectsRequest;
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

it('lists only the projects with a skipped worklog and no customer and budget when opened with that filter', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    MockClient::global([GetProjectsRequest::class => MockResponse::make(['values' => []])]);
    $withoutBudget = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => Customer::factory()->create()->id]);
    $withoutBudget->forceFill(['worklog_skipped_at' => now()])->save();
    $neverLoggedOn = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'BETA', 'project_name' => 'Beta']);

    // When
    $page = Livewire::withQueryParams(['filters' => ['skipped_worklogs' => ['isActive' => '1']]])->test(ProjectMappingPage::class, ['record' => $jira->id]);

    // Then
    $page->assertCanSeeTableRecords([$withoutBudget])->assertCanNotSeeTableRecords([$neverLoggedOn]);
});
