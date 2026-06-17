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

test('budget that renews during the month has renewed credit', function () {
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
    expect($budgetMutation->renewedCredit->toInt())->toEqual(60);
});

test('correct values are used from start and end periods from budget that renews during the month', function () {
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
            'started_at' => Carbon::now()->startOfMonth()->subDay(),
            'minutes_spent' => 30,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->setDay(16),
            'minutes_spent' => 40,
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
    expect($budgetMutation->startBalance->toInt())->toEqual(30);
    expect($budgetMutation->expiredCredit->toInt())->toEqual(30);
    expect($budgetMutation->endBalance->toInt())->toEqual(20);
});
