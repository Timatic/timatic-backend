<?php

use App\Listeners\AddEmergencyShiftDataToEntry;
use App\Listeners\AddTicketDataToEntry;
use App\Models\Budget;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\User;
use App\Services\BudgetVersionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns hourly rate without budget', function () {
    mock(AddEmergencyShiftDataToEntry::class);
    mock(AddTicketDataToEntry::class);

    /** @var Customer $customer */
    $customer = Customer::factory()->create([
        'external_id' => 123,
        'hourly_rate' => '99',
    ]);

    /** @var Entry $entry */
    $entry = Entry::query()->make([
        'customer_id' => $customer->id,
        'entry_type' => 'regular',
    ]);

    $entry->save();
    $entry->refresh();

    expect((string) $entry->getHourlyRateBigDecimal())->toEqual(99);
});

it('returns hourly rate with budget', function () {
    Event::fake();

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var User $supervisor */
    $supervisor = User::factory()->create();

    /** @var Budget $budget */
    $budget = Budget::query()->create([
        'budget_type_id' => 'support',
        'customer_id' => $customer->id,
        'started_at' => Carbon::now()->subMonth(),
        'ended_at' => Carbon::now()->addYear(),
        'renewal_frequency' => 'monthly',
        'service_manager_user_id' => $supervisor->id,
        'supervisor_user_id' => $supervisor->id,
    ]);

    /** @var BudgetVersionService $budgetVersionService */
    $budgetVersionService = resolve(BudgetVersionService::class);

    $budgetVersionService->createAndReplaceVersion($budget, [
        'title' => $this->faker->title(),
        'total_price' => $this->faker->numberBetween(1000, 20000),
        'initial_minutes' => $this->faker->numberBetween(100, 500),
    ]);

    /** @var Entry $entry */
    $entry = Entry::query()->create([
        'budget_id' => $budget->id,
        'customer_id' => $customer->id,
        'entry_type' => 'regular',
    ]);

    expect($entry->getHourlyRateBigDecimal())->toEqual($budget->getHourlyRateBigDecimal());
});
