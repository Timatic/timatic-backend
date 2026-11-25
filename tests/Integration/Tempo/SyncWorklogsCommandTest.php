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
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserByAccountIdRequest;
use Timatic\Tempo\Requests\GetWorklogsRequest;
use Timatic\Tempo\Requests\GetDeletedWorklogsRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates an entry with the real author for a worklog changed in Tempo and stores the next sync position', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $tempo = Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_timatic' => true, 'worklog_sync_updated_since' => 1794870000000, 'worklog_sync_deleted_since' => 1794870000000]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    $user = User::factory()->create(['email' => 'ann@example.com']);
    MockClient::global([
        GetWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 1, 'offset' => 0, 'limit' => 1000], 'results' => [[
            'tempoWorklogId' => 70002,
            'issue' => ['id' => 21840],
            'timeSpentSeconds' => 5400,
            'startDateTimeUtc' => '2026-11-18T08:00:00Z',
            'description' => 'Fixed the login',
            'updatedAt' => '2026-11-18T12:00:00Z',
            'author' => ['accountId' => 'acc-1'],
        ]]]),
        GetDeletedWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 0], 'results' => []]),
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '21840',
            'key' => 'ACME-12',
            'fields' => ['summary' => 'Login fails on Safari', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    $this->artisan('tempo:sync-worklogs')->assertSuccessful();

    // Then
    $entry = Entry::query()->sole();
    expect($entry->external_id)->toBe('70002')
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
        ->and($tempo->refresh()->config['worklog_sync_updated_since'])->toBe(1795003200000)
        ->and($tempo->config['worklog_sync_deleted_since'])->toBe(1794870000000);
});

it('updates the entry of a worklog that was changed in Tempo', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_timatic' => true, 'worklog_sync_updated_since' => 1794873600000, 'worklog_sync_deleted_since' => 1794873600000]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    User::factory()->create(['email' => 'ann@example.com']);
    $entry = Entry::factory()->create(['external_id' => '70002', 'description' => 'Old description', 'started_at' => now()->subDay()]);
    MockClient::global([
        GetWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 1], 'results' => [[
            'tempoWorklogId' => 70002,
            'issue' => ['id' => 21840],
            'timeSpentSeconds' => 7200,
            'startDateTimeUtc' => now()->subDay()->setTime(8, 0)->toIso8601ZuluString(),
            'description' => 'New description',
            'updatedAt' => '2026-11-18T12:00:00Z',
            'author' => ['accountId' => 'acc-1'],
        ]]]),
        GetDeletedWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 0], 'results' => []]),
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '21840',
            'key' => 'ACME-12',
            'fields' => ['summary' => 'Login fails on Safari', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    $this->artisan('tempo:sync-worklogs')->assertSuccessful();

    // Then
    expect(Entry::query()->count())->toBe(1)
        ->and($entry->refresh()->description)->toBe('New description')
        ->and($entry->minutes_spent)->toBe(120);
});

it('removes the entry of a worklog that was deleted in Tempo', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $tempo = Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_timatic' => true, 'worklog_sync_updated_since' => 1794873600000, 'worklog_sync_deleted_since' => 1794873600000]]);
    $entry = Entry::factory()->create(['external_id' => '70002', 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);
    MockClient::global([
        GetWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 0], 'results' => []]),
        GetDeletedWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 1, 'limit' => 1000], 'results' => [
            ['deletedAt' => '2026-11-18T13:00:00.000000', 'tempoWorklogId' => '70002', 'jiraWorklogId' => '108746'],
        ]]),
    ]);

    // When
    $this->artisan('tempo:sync-worklogs')->assertSuccessful();

    // Then
    $this->assertSoftDeleted($entry);
    expect($tempo->refresh()->config['worklog_sync_deleted_since'])->toBe(1795006800000);
});

it('keeps an invoiced entry when its worklog is deleted in Tempo', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_timatic' => true, 'worklog_sync_updated_since' => 1794873600000, 'worklog_sync_deleted_since' => 1794873600000]]);
    $entry = Entry::factory()->create(['external_id' => '70002', 'invoiced_at' => now(), 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);
    MockClient::global([
        GetWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 0], 'results' => []]),
        GetDeletedWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 1], 'results' => [
            ['deletedAt' => '2026-11-18T13:00:00.000000', 'tempoWorklogId' => '70002', 'jiraWorklogId' => '108746'],
        ]]),
    ]);

    // When
    $this->artisan('tempo:sync-worklogs')->assertSuccessful();

    // Then
    $this->assertNotSoftDeleted($entry);
});

it('records a worklog on a mirrored issue against the original issue of the other Jira instance', function () {
    // Given
    $pionect = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $thesis = Integration::factory()->create(['type' => 'jira', 'config' => []]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $pionect->id, 'sync_to_timatic' => true, 'mirrored_issues_enabled' => true, 'mirrored_issue_prefixes' => ['ABC'], 'worklog_sync_updated_since' => 1794873600000, 'worklog_sync_deleted_since' => 1794873600000]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => now()->subYear(), 'effective_to' => null]))->create();
    ProjectMapping::query()->create(['integration_id' => $thesis->id, 'project_key' => 'ABC', 'project_name' => 'Abc', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    User::factory()->create(['email' => 'ann@example.com']);
    MockClient::global([
        GetWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 1], 'results' => [[
            'tempoWorklogId' => 70003,
            'issue' => ['id' => 5001],
            'timeSpentSeconds' => 3600,
            'startDateTimeUtc' => '2026-11-18T08:00:00Z',
            'description' => '',
            'updatedAt' => '2026-11-18T12:00:00Z',
            'author' => ['accountId' => 'acc-1'],
        ]]]),
        GetDeletedWorklogsRequest::class => MockResponse::make(['metadata' => ['count' => 0], 'results' => []]),
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '5001',
            'key' => 'PIO-7',
            'fields' => ['summary' => 'ABC-42 Fix login', 'issuetype' => ['name' => 'Task'], 'created' => '2026-09-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    $this->artisan('tempo:sync-worklogs')->assertSuccessful();

    // Then
    $entry = Entry::query()->sole();
    expect($entry->ticket_number)->toBe('ABC-42')
        ->and($entry->ticket_id)->toBe('ABC-42')
        ->and($entry->ticket_title)->toBe('Fix login')
        ->and($entry->customer_id)->toBe($budget->customer_id)
        ->and($entry->budget_id)->toBe($budget->id);
});
