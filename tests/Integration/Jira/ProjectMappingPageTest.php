<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
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

it('lists only the projects another page links to by id, also the ones with a customer', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $withoutBudget = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => Customer::factory()->create()->id]);
    $unmapped = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'BETA', 'project_name' => 'Beta']);
    $notLinked = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'GAMMA', 'project_name' => 'Gamma']);

    // When
    $page = Livewire::withQueryParams(['filters' => ['id' => ['ids' => "{$withoutBudget->id},{$unmapped->id}"]]])->test(ProjectMappingPage::class, ['record' => $jira->id]);

    // Then
    $page->assertCanSeeTableRecords([$withoutBudget, $unmapped])->assertCanNotSeeTableRecords([$notLinked]);
});

it('leaves jira alone when opening a page that already has projects', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $mockClient = MockClient::global();

    // When
    Livewire::test(ProjectMappingPage::class, ['record' => $jira->id])->assertSuccessful();

    // Then
    $mockClient->assertNothingSent();
});

it('shows when the projects were last refreshed', function () {
    // Given
    $this->travelTo(CarbonImmutable::parse('2026-11-26T10:30:00Z'));
    config()->set('timatic.preferred_timezone', 'Europe/Amsterdam');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    MockClient::global([GetProjectsRequest::class => MockResponse::make(['values' => [['key' => 'ACME', 'name' => 'Acme']]])]);

    // When
    $page = Livewire::test(ProjectMappingPage::class, ['record' => $jira->id])->callAction('refresh');

    // Then
    $page->assertSee(__('jira::jira.common.last_synced', ['at' => '26-11-2026 11:30']));
});

it('lists the projects without a budget by default and narrows them to the ones without a customer', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $mapped = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    $withoutBudget = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'BETA', 'project_name' => 'Beta', 'customer_id' => $budget->customer_id]);
    $withoutCustomer = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'GAMMA', 'project_name' => 'Gamma']);

    // When
    $page = Livewire::test(ProjectMappingPage::class, ['record' => $jira->id]);

    // Then
    $page->assertCanSeeTableRecords([$withoutBudget, $withoutCustomer])->assertCanNotSeeTableRecords([$mapped])
        ->filterTable('without_customer')
        ->assertCanSeeTableRecords([$withoutCustomer])->assertCanNotSeeTableRecords([$withoutBudget, $mapped]);
});

it('links one project to a customer and budget from its row', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $project = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);

    // When
    Livewire::test(ProjectMappingPage::class, ['record' => $jira->id])
        ->callAction(TestAction::make('assign')->table($project), ['customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);

    // Then
    expect($project->refresh()->only(['customer_id', 'budget_id']))->toBe(['customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
});

it('links no budget to a project while no customer is chosen', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $project = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);

    // When
    Livewire::test(ProjectMappingPage::class, ['record' => $jira->id])
        ->callAction(TestAction::make('assign')->table($project), ['budget_id' => $budget->id]);

    // Then
    expect($project->refresh()->only(['customer_id', 'budget_id']))->toBe(['customer_id' => null, 'budget_id' => null]);
});
