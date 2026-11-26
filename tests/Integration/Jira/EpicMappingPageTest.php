<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Integration;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Concerns\LoginUser;
use Timatic\Jira\Filament\Pages\EpicMappingPage;
use Timatic\Jira\Models\EpicMapping;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssuesRequest;

uses(LoginUser::class);
uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('loads the epics of an added project and lists only those', function () {
    // Given
    $this->travelTo(CarbonImmutable::parse('2026-11-26T10:30:00Z'));
    config()->set('timatic.preferred_timezone', 'Europe/Amsterdam');
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $support = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support']);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $epicOfOtherProject = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'ACME-1', 'epic_name' => 'Webshop']);
    MockClient::global([
        GetIssuesRequest::class => fn (PendingRequest $request) => MockResponse::make(['issues' => $request->query()->get('jql') === 'project in ("SUP") AND hierarchyLevel = 1'
            ? [['id' => '37100', 'key' => 'SUP-3', 'fields' => ['summary' => 'Acme', 'issuetype' => ['name' => 'Epic', 'hierarchyLevel' => 1], 'created' => '2026-09-01T10:00:00.000+0000']]]
            : []]),
    ]);

    // When
    $page = Livewire::test(EpicMappingPage::class, ['record' => $jira->id])->callAction('add_project', ['project_mapping_id' => $support->id]);

    // Then
    $epic = EpicMapping::query()->where('epic_key', 'SUP-3')->sole();
    $page->assertCanSeeTableRecords([$epic])->assertCanNotSeeTableRecords([$epicOfOtherProject])
        ->assertSee(__('jira::jira.common.last_synced', ['at' => '26-11-2026 11:30']));
    expect($epic->epic_name)->toBe('Acme')
        ->and($support->refresh()->is_mapped_per_epic)->toBeTrue();
});

it('hides the epics of a removed project and keeps their links', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $support = ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support', 'is_mapped_per_epic' => true]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $epic = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-3', 'epic_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);

    // When
    $page = Livewire::test(EpicMappingPage::class, ['record' => $jira->id])
        ->removeTableFilter('without_budget')
        ->callAction('remove_project', ['project_mapping_id' => $support->id]);

    // Then
    $page->assertCanNotSeeTableRecords([$epic]);
    expect($support->refresh()->is_mapped_per_epic)->toBeFalse()
        ->and($epic->refresh()->budget_id)->toBe($budget->id);
});

it('lists the epics without a budget by default and narrows them to the ones without a customer', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support', 'is_mapped_per_epic' => true]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $mapped = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-3', 'epic_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    $withoutBudget = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-4', 'epic_name' => 'Beta', 'customer_id' => $budget->customer_id]);
    $withoutCustomer = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-5', 'epic_name' => 'Gamma']);

    // When
    $page = Livewire::test(EpicMappingPage::class, ['record' => $jira->id]);

    // Then
    $page->assertCanSeeTableRecords([$withoutBudget, $withoutCustomer])->assertCanNotSeeTableRecords([$mapped])
        ->filterTable('without_customer')
        ->assertCanSeeTableRecords([$withoutCustomer])->assertCanNotSeeTableRecords([$withoutBudget, $mapped]);
});

it('links one epic to a customer and budget from its row', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support', 'is_mapped_per_epic' => true]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    $epic = EpicMapping::query()->create(['integration_id' => $jira->id, 'epic_key' => 'SUP-3', 'epic_name' => 'Acme']);

    // When
    Livewire::test(EpicMappingPage::class, ['record' => $jira->id])
        ->callAction(TestAction::make('assign')->table($epic), ['customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);

    // Then
    expect($epic->refresh()->only(['customer_id', 'budget_id']))->toBe(['customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
});

it('reports the reason Jira gives when the epics cannot be loaded', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'GONE', 'project_name' => 'Gone', 'is_mapped_per_epic' => true]);
    MockClient::global([
        GetIssuesRequest::class => MockResponse::make(['errorMessages' => ["The value 'GONE' does not exist for the field 'project'."]], 400),
    ]);

    // When
    $page = Livewire::test(EpicMappingPage::class, ['record' => $jira->id])->callAction('refresh');

    // Then
    $page->assertNotified(Notification::make()
        ->title(__('jira::jira.epic_mapping.sync_failed_title'))
        ->body("The value 'GONE' does not exist for the field 'project'.")
        ->danger());
});
