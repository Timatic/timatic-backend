<?php

use App\Models\Budget;
use App\Models\BudgetType;
use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\GetLeaveRequestsRequest;
use Timatic\Nmbrs\Requests\GetPersonalInfoRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

/**
 * Get the leave budget the same way the command does (first seeded/existing one).
 */
function getLeaveBudget(): Budget
{
    /** @var Budget */
    return Budget::whereHas('budgetType', fn ($q) => $q->where('id', BudgetType::LEAVE))->firstOrFail();
}

it('creates a leave entry for employees on leave today', function () {
    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';
    $today = Carbon::today('Europe/Amsterdam')->toDateString();

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
        ],
    ]);

    Budget::factory()->create(['budget_type_id' => BudgetType::LEAVE]);
    $leaveBudget = getLeaveBudget();
    $user = User::factory()->create(['email' => $userEmail]);

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
        GetLeaveRequestsRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'EmployeeLeaveRequests' => [[
                    'leaveRequestsId' => 'lr-001',
                    'startDate' => $today,
                    'endDate' => $today,
                    'hours' => 8.0,
                    'status' => 'Approved',
                    'type' => 'Withdrawal',
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:sync-leave')->assertSuccessful();

    expect(
        Entry::where('user_id', $user->id)
            ->where('budget_id', $leaveBudget->id)
            ->whereDate('started_at', $today)
            ->exists()
    )->toBeTrue();
});

it('does not create duplicate leave entries for the same day', function () {
    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';
    $today = Carbon::today('Europe/Amsterdam')->toDateString();

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
        ],
    ]);

    Budget::factory()->create(['budget_type_id' => BudgetType::LEAVE]);
    $leaveBudget = getLeaveBudget();
    $user = User::factory()->create(['email' => $userEmail]);

    // Pre-existing leave entry for today
    Entry::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $leaveBudget->id,
        'started_at' => Carbon::today('Europe/Amsterdam')->setHour(9),
        'ended_at' => Carbon::today('Europe/Amsterdam')->setHour(17),
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
        GetLeaveRequestsRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'EmployeeLeaveRequests' => [[
                    'leaveRequestsId' => 'lr-001',
                    'startDate' => $today,
                    'endDate' => $today,
                    'hours' => 8.0,
                    'status' => 'Approved',
                    'type' => 'Withdrawal',
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:sync-leave')->assertSuccessful();

    expect(
        Entry::where('user_id', $user->id)
            ->where('budget_id', $leaveBudget->id)
            ->whereDate('started_at', $today)
            ->count()
    )->toBe(1);
});

it('skips leave for employees not found as Timatic users', function () {
    $employeeId = 'emp-999';
    $today = Carbon::today('Europe/Amsterdam')->toDateString();

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
        ],
    ]);

    Budget::factory()->create(['budget_type_id' => BudgetType::LEAVE]);

    // No Timatic user matching the NMBRS employee email
    MockClient::global([
        GetPersonalInfoRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'info' => [[
                    'period' => ['year' => 2026, 'period' => 4],
                    'contactInfo' => ['businessEmail' => 'nobody@example.com'],
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
        GetLeaveRequestsRequest::class => MockResponse::make([
            'data' => [[
                'employeeId' => $employeeId,
                'EmployeeLeaveRequests' => [[
                    'leaveRequestsId' => 'lr-001',
                    'startDate' => $today,
                    'endDate' => $today,
                    'hours' => 8.0,
                ]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:sync-leave')->assertSuccessful();

    expect(Entry::whereDate('started_at', $today)->count())->toBe(0);
});
