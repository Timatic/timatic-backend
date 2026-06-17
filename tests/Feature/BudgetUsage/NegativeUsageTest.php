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

test('budget with negative balance will not report correction entry as used budget', function () {
    Event::fake();

    $budgetStart = Carbon::now()->subMonths(6);
    $budgetEnd = Carbon::now()->addMonths(6);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 60,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->setDay(1)->subMonth(),
            'minutes_spent' => 120,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now(),
            'minutes_spent' => -70,
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->last();

    expect($budgetMutations)->not->toBeEmpty();
    expect($budgetMutation->usedCredit->toInt())->toEqual(-10);
    expect($budgetMutation->usedOutOfBudget->toInt())->toEqual(-60);
});

test('budget with negative balance will not report partial correction entry as used budget', function () {
    Event::fake();

    $budgetStart = Carbon::now()->subMonths(6);
    $budgetEnd = Carbon::now()->addMonths(6);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 60,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now()->setDay(1)->subMonth(),
            'minutes_spent' => 120,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now(),
            'minutes_spent' => -40,
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->last();

    expect($budgetMutations)->not->toBeEmpty();
    expect($budgetMutation->usedCredit->toInt())->toEqual(0);
    expect($budgetMutation->usedOutOfBudget->toInt())->toEqual(-40);
});

test('a correction entry on a new budget should show as used credit', function () {
    Event::fake();

    $budgetStart = Carbon::now()->subMonths(6);
    $budgetEnd = Carbon::now()->addMonths(6);

    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 60,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->has(Entry::factory()->state([
            'started_at' => Carbon::now(),
            'minutes_spent' => -40,
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->last();

    expect($budgetMutations)->not->toBeEmpty();
    expect($budgetMutation->usedCredit->toInt())->toEqual(-40);
    expect($budgetMutation->usedOutOfBudget->toInt())->toEqual(0);
});
