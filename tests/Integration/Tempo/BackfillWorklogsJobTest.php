<?php

declare(strict_types=1);

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Timatic\Jira\Models\ProjectMapping;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserByAccountIdRequest;
use Timatic\Tempo\Jobs\BackfillWorklogsJob;
use Timatic\Tempo\Requests\GetWorklogsRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('imports the worklogs of the given days without touching the sync position', function () {
    // Given
    $jira = Integration::factory()->create(['type' => 'jira', 'config' => ['access_token' => 'token', 'cloud_id' => 'cloud-1', 'expires_at' => now()->addHour()->timestamp]]);
    $tempo = Integration::factory()->create(['type' => 'tempo', 'config' => ['api_token' => 'tempo-token', 'jira_integration_id' => $jira->id, 'worklog_sync_updated_since' => 1794873600000]]);
    $budget = Budget::factory()->has(BudgetVersion::factory()->state(['effective_from' => '2026-01-01', 'effective_to' => null]))->create(['started_at' => '2026-01-01']);
    ProjectMapping::query()->create(['integration_id' => $jira->id, 'project_key' => 'ACME', 'project_name' => 'Acme', 'customer_id' => $budget->customer_id, 'budget_id' => $budget->id]);
    User::factory()->create(['email' => 'ann@example.com']);
    $requestedPeriod = [];
    MockClient::global([
        GetWorklogsRequest::class => function (PendingRequest $request) use (&$requestedPeriod) {
            $requestedPeriod = [$request->query()->get('from'), $request->query()->get('to'), $request->query()->get('updatedFrom')];

            return MockResponse::make(['metadata' => ['count' => 1], 'results' => [[
                'tempoWorklogId' => 70001,
                'issue' => ['id' => 21840],
                'timeSpentSeconds' => 3600,
                'startDateTimeUtc' => '2026-06-18T08:00:00Z',
                'description' => 'Fixed the login',
                'updatedAt' => '2026-06-18T12:00:00Z',
                'author' => ['accountId' => 'acc-1'],
            ]]]);
        },
        GetIssuesRequest::class => MockResponse::make(['issues' => [[
            'id' => '21840',
            'key' => 'ACME-12',
            'fields' => ['summary' => 'Login fails on Safari', 'issuetype' => ['name' => 'Bug'], 'created' => '2026-05-01T10:00:00.000+0000'],
        ]]]),
        GetUserByAccountIdRequest::class => MockResponse::make(['accountId' => 'acc-1', 'emailAddress' => 'ann@example.com']),
    ]);

    // When
    BackfillWorklogsJob::dispatchSync($tempo->id, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

    // Then
    expect($requestedPeriod)->toBe(['2026-06-01', '2026-06-30', null])
        ->and(Entry::query()->pluck('external_id')->all())->toBe(['70001'])
        ->and($tempo->refresh()->config['worklog_sync_updated_since'])->toBe(1794873600000);
});
