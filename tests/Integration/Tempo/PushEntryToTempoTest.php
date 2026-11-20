<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserByEmailRequest;
use Timatic\Tempo\Requests\CreateWorklogRequest;
use Timatic\Tempo\Requests\DeleteWorklogRequest;
use Timatic\Tempo\Requests\UpdateWorklogRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('creates a Tempo worklog of the user for a saved entry and stores the Tempo id', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_tempo' => true]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $user = User::factory()->create(['email' => 'ann@example.com']);
    $mock = MockClient::global([
        GetIssuesRequest::class => MockResponse::make(['issues' => [['id' => '21840', 'key' => 'ACME-12', 'fields' => ['summary' => 'Login fails', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000']]]]),
        GetUserByEmailRequest::class => MockResponse::make([['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']]),
        CreateWorklogRequest::class => MockResponse::make(['tempoWorklogId' => 70010]),
    ]);

    // When
    $entry = Entry::factory()->create([
        'user_id' => $user->id,
        'ticket_number' => 'ACME-12',
        'description' => 'Fixed the login',
        'started_at' => '2026-11-18 08:00:00',
        'ended_at' => '2026-11-18 09:30:00',
    ]);

    // Then
    $mock->assertSent(fn (CreateWorklogRequest $request): bool => $request->body()->all() === [
        'issueId' => 21840,
        'authorAccountId' => 'acc-1',
        'startDate' => '2026-11-18',
        'startTime' => '09:00:00',
        'timeSpentSeconds' => 5400,
        'description' => 'Fixed the login',
    ]);
    expect($entry->refresh()->external_id)->toBe('70010');
});

it('updates the Tempo worklog of an entry that already has one', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_tempo' => true]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $user = User::factory()->create(['email' => 'ann@example.com']);
    Context::add('entry_sync_source', 'tempo');
    $entry = Entry::factory()->create(['user_id' => $user->id, 'ticket_number' => 'ACME-12', 'external_id' => '70010']);
    Context::forget('entry_sync_source');
    $mock = MockClient::global([
        GetIssuesRequest::class => MockResponse::make(['issues' => [['id' => '21840', 'key' => 'ACME-12', 'fields' => ['summary' => 'Login fails', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-09-01T10:00:00.000+0000']]]]),
        GetUserByEmailRequest::class => MockResponse::make([['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']]),
        UpdateWorklogRequest::class => MockResponse::make(['tempoWorklogId' => 70010]),
    ]);

    // When
    $entry->update(['description' => 'Fixed the login for real']);

    // Then
    $mock->assertSent(fn (UpdateWorklogRequest $request): bool => $request->resolveEndpoint() === '/4/worklogs/70010'
        && $request->body()->get('description') === 'Fixed the login for real');
});

it('deletes the Tempo worklog when its entry is deleted', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_tempo' => true]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    Context::add('entry_sync_source', 'tempo');
    $entry = Entry::factory()->create(['ticket_number' => 'ACME-12', 'external_id' => '70010']);
    Context::forget('entry_sync_source');
    $mock = MockClient::global([DeleteWorklogRequest::class => MockResponse::make([], 204)]);

    // When
    $entry->delete();

    // Then
    $mock->assertSent(fn (DeleteWorklogRequest $request): bool => $request->resolveEndpoint() === '/4/worklogs/70010');
});

it('does not push entries that the worklog sync imported', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_tempo' => true]]);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme']);
    $mock = MockClient::global([]);
    Context::add('entry_sync_source', 'tempo');

    // When
    Entry::factory()->create(['ticket_number' => 'ACME-12']);

    // Then
    Context::forget('entry_sync_source');
    $mock->assertNothingSent();
});

it('leaves entries on a project the linked Jira instance does not map alone', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'sync_to_tempo' => true]]);
    $mock = MockClient::global([]);

    // When
    $entry = Entry::factory()->create(['ticket_number' => 'OTHER-1']);

    // Then
    $mock->assertNothingSent();
    expect($entry->refresh()->external_id)->toBeNull();
});
