<?php

use App\Mail\BudgetMonthlyBalance;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('run command successfully', function () {
    $this->artisan('budget:balance-notification')->assertSuccessful();
});

test('send email', function () {
    Mail::fake();
    Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $budgets = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => Carbon::now()
                ->setDay(1)
                ->setMonth(1)
                ->format('Y-m-d'),
            'effective_to' => Carbon::now()
                ->addYear()
                ->setDay(1)
                ->setMonth(1)
                ->format('Y-m-d'),
        ]))
        ->has(Customer::factory()->state([
            'service_manager_user_id' => $user->id,
        ]))
        ->count(3)
        ->create([
            'supervisor_user_id' => $user->id,
        ]);

    $this->artisan('budget:balance-notification')->assertSuccessful();

    Mail::assertSent(BudgetMonthlyBalance::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
});
