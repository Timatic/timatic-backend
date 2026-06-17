<?php

use App\DataTransferObjects\BudgetMutation;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Entry;
use App\Services\BudgetUsageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('with negative start balance', function () {
    Event::fake();
    Budget::query()->delete();

    $budgetStart = Carbon::now()->startOfMonth()->subMonths(6);
    $budgetEnd = Carbon::now()->startOfMonth()->addMonths(6);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 60,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->startOfMonth()->subMonth(),
            'minutes_spent' => 70,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->startOfMonth(),
            'minutes_spent' => 40,
        ]))
        ->create([
            'renewal_frequency' => 'yearly',
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());
    expect($budgetMutations)->toHaveCount(1);

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->first();
    expect($budgetMutation->usedCredit->toInt())->toEqual(0);
    expect($budgetMutation->usedOutOfBudget->toInt())->toEqual(40);
});

test('depletion of two periods in the same month add up', function () {
    config(['timatic.feature.align_periods_to_month_start' => false]);

    Budget::query()->delete();
    Event::fake();

    $budgetStart = Carbon::now()->subMonths(6)->setDay(15);
    $budgetEnd = Carbon::now()->addMonths(6)->setDay(14);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 60,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->setDay(1),
            'minutes_spent' => 80,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->setDay(16),
            'minutes_spent' => 80,
        ]))
        ->create([
            'renewal_frequency' => 'monthly',
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());
    expect($budgetMutations)->toHaveCount(1);

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->first();
    expect($budgetMutation->usedCredit->toInt())->toEqual(2 * 60);
    // both periods where complete used in this month
    expect($budgetMutation->usedOutOfBudget->toInt())->toEqual(40);
});
