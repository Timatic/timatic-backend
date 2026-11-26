<?php

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

it('loads the epics of the projects that are mapped per epic', function () {
    // Given
    $this->loginUser(permissions: ['integrations.read']);
    Filament::setCurrentPanel('admin');
    $jira = Integration::create(['title' => 'Jira', 'type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'SUP', 'project_name' => 'Support', 'is_mapped_per_epic' => true]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    MockClient::global([
        GetIssuesRequest::class => fn (PendingRequest $request) => MockResponse::make(['issues' => $request->query()->get('jql') === 'project in ("SUP") AND hierarchyLevel = 1'
            ? [['id' => '37100', 'key' => 'SUP-3', 'fields' => ['summary' => 'Acme', 'issuetype' => ['name' => 'Epic', 'hierarchyLevel' => 1], 'created' => '2026-09-01T10:00:00.000+0000']]]
            : []]),
    ]);

    // When
    $page = Livewire::test(EpicMappingPage::class, ['record' => $jira->id]);

    // Then
    $page->assertCanSeeTableRecords(EpicMapping::query()->get());
    expect(EpicMapping::query()->get(['epic_key', 'epic_name', 'customer_id'])->toArray())->toBe([
        ['epic_key' => 'SUP-3', 'epic_name' => 'Acme', 'customer_id' => null],
    ]);
});
