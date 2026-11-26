<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Integration;
use Filament\Facades\Filament;
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
    $page->assertCanSeeTableRecords([$epic])->assertCanNotSeeTableRecords([$epicOfOtherProject]);
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
        ->removeTableFilter('unmapped')
        ->callAction('remove_project', ['project_mapping_id' => $support->id]);

    // Then
    $page->assertCanNotSeeTableRecords([$epic]);
    expect($support->refresh()->is_mapped_per_epic)->toBeFalse()
        ->and($epic->refresh()->budget_id)->toBe($budget->id);
});
