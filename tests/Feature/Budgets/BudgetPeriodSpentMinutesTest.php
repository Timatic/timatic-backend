<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('takes entries before the budget start date and after budget end date into account', function () {
    Event::fake();

    $budgetStart = Carbon::today()->subMonth();
    $budgetEnd = Carbon::today()->addMonth();

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => $budgetStart->clone()->subDay(),
            'minutes_spent' => 10,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now(),
            'minutes_spent' => 10,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => $budgetEnd->clone()->addDay(),
            'minutes_spent' => 10,
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    $period = $budget->getPeriodAt(Carbon::now());

    expect($period->getSpentMinutes())->toEqual(30);
});

it('doesnt count minutes spent on deleted entries', function () {
    Event::fake();

    $budgetStart = Carbon::today()->subMonth();
    $budgetEnd = Carbon::today()->addMonth();

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => $budgetStart->clone()->subDay(),
            'minutes_spent' => 10,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => $budgetEnd->clone()->addDay(),
            'minutes_spent' => 10,
            'deleted_at' => Carbon::now(),
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    $period = $budget->getPeriodAt(Carbon::now());

    expect($period->getSpentMinutes())->toEqual(10);
});
