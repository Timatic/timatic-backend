<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetDeletedWorklogIdsRequest;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUpdatedWorklogIdsRequest;
use Timatic\Jira\Requests\GetUserByAccountIdRequest;
use Timatic\Jira\Requests\GetWorklogsRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates an entry for a worklog updated in Jira and stores the next sync position', function () {
    // Given
    $integration = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp, 'worklog_sync_enabled' => true]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    ProjectMapping::query()->create(['integration_id' => $integration->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    $user = User::factory()->create(['email' => 'ann@example.com']);
    MockClient::global([
        GetUpdatedWorklogIdsRequest::class => MockResponse::make(['values' => [['worklogId' => 1001, 'updatedTime' => 1763500000000]], 'until' => 1763500000000, 'lastPage' => true]),
        GetDeletedWorklogIdsRequest::class => MockResponse::make(['values' => [], 'until' => 1763600000000, 'lastPage' => true]),
        GetWorklogsRequest::class => MockResponse::make([[
            'id' => '1001',
            'issueId' => '5001',
            'author' => ['accountId' => 'acc-1'],
            'started' => '2026-11-18T09:00:00.000+0100',
            'timeSpentSeconds' => 5400,
            'comment' => 'Fixed the login',
        ]]),
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '5001',
            'key' => 'ACME-12',
            'fields' => ['summary' => 'Login fails on Safari', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    $this->artisan('jira:sync-worklogs')->assertSuccessful();

    // Then
    $entry = Entry::query()->sole();
    expect($entry->external_source)->toBe('jira')
        ->and($entry->external_id)->toBe('1001')
        ->and($entry->ticket_number)->toBe('ACME-12')
        ->and($entry->ticket_title)->toBe('Login fails on Safari')
        ->and($entry->ticket_type)->toBe('Bug')
        ->and($entry->customer_id)->toBe($budget->customer_id)
        ->and($entry->budget_id)->toBe($budget->id)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->description)->toBe('Fixed the login')
        ->and($entry->started_at->toIso8601String())->toBe('2026-11-18T08:00:00+00:00')
        ->and($entry->ended_at->toIso8601String())->toBe('2026-11-18T09:30:00+00:00')
        ->and($entry->minutes_spent)->toBe(90)
        ->and($integration->refresh()->config['worklog_sync_updated_since'])->toBe(1763500000000)
        ->and($integration->config['worklog_sync_deleted_since'])->toBe(1763600000000);
});

it('updates the entry of a worklog that was changed in Jira', function () {
    // Given
    $integration = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp, 'worklog_sync_enabled' => true]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    ProjectMapping::query()->create(['integration_id' => $integration->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    User::factory()->create(['email' => 'ann@example.com']);
    $entry = Entry::factory()->create(['external_source' => 'jira', 'external_id' => '1001', 'description' => 'Old description', 'started_at' => now()->subDay()]);
    MockClient::global([
        GetUpdatedWorklogIdsRequest::class => MockResponse::make(['values' => [['worklogId' => 1001]], 'until' => 1763500000000, 'lastPage' => true]),
        GetDeletedWorklogIdsRequest::class => MockResponse::make(['values' => [], 'until' => 1763500000000, 'lastPage' => true]),
        GetWorklogsRequest::class => MockResponse::make([[
            'id' => '1001',
            'issueId' => '5001',
            'author' => ['accountId' => 'acc-1'],
            'started' => now()->subDay()->setTime(9, 0)->toIso8601String(),
            'timeSpentSeconds' => 7200,
            'comment' => 'New description',
        ]]),
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '5001',
            'key' => 'ACME-12',
            'fields' => ['summary' => 'Login fails on Safari', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    $this->artisan('jira:sync-worklogs')->assertSuccessful();

    // Then
    expect(Entry::query()->count())->toBe(1)
        ->and($entry->refresh()->description)->toBe('New description')
        ->and($entry->minutes_spent)->toBe(120);
});

it('removes the entry of a worklog that was deleted in Jira', function () {
    // Given
    Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp, 'worklog_sync_enabled' => true]]);
    $entry = Entry::factory()->create(['external_source' => 'jira', 'external_id' => '1001', 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);
    MockClient::global([
        GetUpdatedWorklogIdsRequest::class => MockResponse::make(['values' => [], 'until' => 1763500000000, 'lastPage' => true]),
        GetDeletedWorklogIdsRequest::class => MockResponse::make(['values' => [['worklogId' => 1001]], 'until' => 1763500000000, 'lastPage' => true]),
    ]);

    // When
    $this->artisan('jira:sync-worklogs')->assertSuccessful();

    // Then
    $this->assertSoftDeleted($entry);
});

it('keeps an invoiced entry when its worklog is deleted in Jira', function () {
    // Given
    Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp, 'worklog_sync_enabled' => true]]);
    $entry = Entry::factory()->create(['external_source' => 'jira', 'external_id' => '1001', 'invoiced_at' => now(), 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);
    MockClient::global([
        GetUpdatedWorklogIdsRequest::class => MockResponse::make(['values' => [], 'until' => 1763500000000, 'lastPage' => true]),
        GetDeletedWorklogIdsRequest::class => MockResponse::make(['values' => [['worklogId' => 1001]], 'until' => 1763500000000, 'lastPage' => true]),
    ]);

    // When
    $this->artisan('jira:sync-worklogs')->assertSuccessful();

    // Then
    $this->assertNotSoftDeleted($entry);
});

it('does not contact Jira while the worklog sync is disabled in the settings', function () {
    // Given
    Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Entry::factory()->create(['external_source' => 'jira', 'external_id' => '1001']);

    // When
    $this->artisan('jira:sync-worklogs')->assertSuccessful();

    // Then
    expect(Entry::query()->count())->toBe(1);
});
