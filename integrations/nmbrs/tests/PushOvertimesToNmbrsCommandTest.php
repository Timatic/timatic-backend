<?php

use App\Models\Entry;
use App\Models\Integration;
use App\Models\Overtime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\CreateVariableHourRequest;
use Timatic\Nmbrs\Requests\GetContractsRequest;
use Timatic\Nmbrs\Requests\GetPersonalInfoRequest;
use Timatic\Nmbrs\Requests\GetVariableHoursRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('pushes approved unexported overtimes to NMBRS and marks them as exported', function () {
    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
            'sync_overtime_enabled' => true,
            'hour_codes' => [
                'fulltime' => [100 => 6100, 135 => 6135, 150 => 6150],
                'parttime' => [100 => 2190, 135 => 2124, 150 => 2120],
            ],
        ],
    ]);

    $user = User::factory()->create(['email' => $userEmail]);

    $overtime = Overtime::factory()->create([
        'entry_id' => Entry::factory()->state([
            'user_id' => $user->id,
            'user_email' => $userEmail,
            'user_full_name' => 'Engineer One',
        ]),
        'approved_at' => now(),
        'exported_at' => null,
        'percentages' => (object) ['regular' => (object) ['percentage' => 100, 'minutes' => 120]],
    ]);

    MockClient::global([
        GetPersonalInfoRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'info' => [[
                    'period' => ['year' => 2026, 'period' => 4],
                    'contactInfo' => ['businessEmail' => $userEmail],
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        GetContractsRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'contracts' => [[
                    'startDate' => '2024-01-01',
                    'endDate' => null,
                    'hoursPerWeek' => 40,
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        GetVariableHoursRequest::class => MockResponse::make([
            'data' => [],
            'pagination' => ['totalPages' => 1],
        ]),
        CreateVariableHourRequest::class => MockResponse::make(['id' => 'new-vh-001'], 201),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:push-overtimes')->assertSuccessful();

    expect(Overtime::find($overtime->id)->exported_at)->not->toBeNull();
});

it('skips overtime for users not found in NMBRS', function () {
    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
            'sync_overtime_enabled' => true,
            'hour_codes' => [
                'fulltime' => [100 => 6100],
                'parttime' => [100 => 2190],
            ],
        ],
    ]);

    $user = User::factory()->create(['email' => 'unknown@example.com']);

    $overtime = Overtime::factory()->create([
        'entry_id' => Entry::factory()->state([
            'user_id' => $user->id,
            'user_email' => 'unknown@example.com',
        ]),
        'approved_at' => now(),
        'exported_at' => null,
        'percentages' => (object) ['regular' => (object) ['percentage' => 100, 'minutes' => 60]],
    ]);

    // Return an employee with a different email — no match for 'unknown@example.com'
    MockClient::global([
        GetPersonalInfoRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => 'emp-999',
                'info' => [[
                    'period' => ['year' => 2026, 'period' => 4],
                    'contactInfo' => ['businessEmail' => 'other@example.com'],
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        GetContractsRequest::class => MockResponse::make([
            'data' => [],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:push-overtimes')->assertSuccessful();

    // Overtime should still be unexported since the user was not in NMBRS
    expect(Overtime::find($overtime->id)->exported_at)->toBeNull();
});

it('skips duplicate variable hour components', function () {
    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
            'sync_overtime_enabled' => true,
            'hour_codes' => [
                'fulltime' => [100 => 6100],
                'parttime' => [100 => 2190],
            ],
        ],
    ]);

    $user = User::factory()->create(['email' => $userEmail]);

    Overtime::factory()->create([
        'entry_id' => Entry::factory()->state([
            'user_id' => $user->id,
            'user_email' => $userEmail,
        ]),
        'approved_at' => now(),
        'exported_at' => null,
        'percentages' => (object) ['regular' => (object) ['percentage' => 100, 'minutes' => 60]],
    ]);

    MockClient::global([
        GetPersonalInfoRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'info' => [[
                    'period' => ['year' => 2026, 'period' => 4],
                    'contactInfo' => ['businessEmail' => $userEmail],
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        GetContractsRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'contracts' => [['startDate' => '2024-01-01', 'endDate' => null, 'hoursPerWeek' => 40]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        // Hour code 6100 already exists — should skip
        GetVariableHoursRequest::class => MockResponse::make([
            'data' => [['hourCode' => 6100, 'hours' => 1.0]],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:push-overtimes')->assertSuccessful();

    // Exported because all percentages were skipped as duplicates but there were no failures
    expect(Overtime::query()->whereNull('exported_at')->count())->toBe(0);
});
