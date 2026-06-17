<?php

use App\Models\Entry;
use App\Models\Integration;
use App\Models\Overtime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Timatic\Nmbrs\Mail\OvertimesEngineerMail;
use Timatic\Nmbrs\Mail\OvertimesManagementMail;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\GetContractsRequest;
use Timatic\Nmbrs\Requests\GetPersonalInfoRequest;

uses(RefreshDatabase::class);

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends management and engineer emails for approved unexported overtimes', function () {
    Mail::fake();

    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';
    $managementEmail = 'manager@example.com';

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
            'management_emails' => $managementEmail,
        ],
    ]);

    $user = User::factory()->create(['email' => $userEmail]);

    Overtime::factory()->create([
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
                'contracts' => [['startDate' => '2024-01-01', 'endDate' => null, 'hoursPerWeek' => 40]],
            ]],
            'pagination' => ['totalPages' => 1],
        ]),
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:send-overtime-emails')->assertSuccessful();

    Mail::assertSent(OvertimesManagementMail::class, fn ($mail) => $mail->hasTo($managementEmail));
    Mail::assertSent(OvertimesEngineerMail::class, fn ($mail) => $mail->hasTo($userEmail));
});

it('skips management email when no management emails are configured', function () {
    Mail::fake();

    $employeeId = 'emp-001';
    $userEmail = 'engineer@example.com';

    $integration = Integration::create([
        'name' => 'NMBRS Test',
        'type' => 'nmbrs',
        'config' => [
            'access_token' => 'test-token',
            'company_id' => 'company-001',
            'expires_at' => Carbon::now()->addHour()->timestamp,
            'management_emails' => '',
        ],
    ]);

    $user = User::factory()->create(['email' => $userEmail]);

    Overtime::factory()->create([
        'entry_id' => Entry::factory()->state([
            'user_id' => $user->id,
            'user_email' => $userEmail,
            'user_full_name' => 'Engineer One',
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
    ]);

    $this->mock(OAuthService::class, function ($mock) use ($integration) {
        $mock->shouldReceive('refreshIfExpired')->once()->andReturn($integration);
    });

    $this->artisan('nmbrs:send-overtime-emails')->assertSuccessful();

    Mail::assertNotSent(OvertimesManagementMail::class);
    Mail::assertSent(OvertimesEngineerMail::class, fn ($mail) => $mail->hasTo($userEmail));
});
