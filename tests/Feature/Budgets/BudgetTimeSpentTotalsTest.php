<?php

use App\Http\Resources\BudgetTimeSpentTotals;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Entry;
use App\Services\TimeSpentTotalsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('returns correct time spent totals for budget', function () {
    Budget::query()->delete();
    Event::fake();

    $service = $this->app->make(TimeSpentTotalsService::class);

    $customer = Customer::factory()->create();
    $budgets = Budget::factory()->state([
        'started_at' => CarbonImmutable::now()->startOfWeek(),
        'ended_at' => CarbonImmutable::now()->addMonth(),
        'renewal_frequency' => null,
    ])
        ->has(BudgetVersion::factory()->state([
            'initial_minutes' => 100,
        ])->count(1))
        ->has(
            Entry::factory()->state([
                'minutes_spent' => 10,
                'started_at' => CarbonImmutable::now(),
                'ended_at' => CarbonImmutable::now()->addDay(),
                'customer_id' => $customer->id,
            ])->count(3)
        )
        ->create();

    $totals = $service->getTimeSpentTotalsPerBudget(
        unit: 'week',
        budgetId: $budgets->first()->id,
    );

    $totals = BudgetTimeSpentTotals::collection($totals);

    expect($totals[0]->remainingMinutes)->toEqual(70);
});
