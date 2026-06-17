<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('get periods from budget', function () {
    Event::fake();

    $budgetStart = Carbon::now()->subMonth(2)->setDay(1);
    $budgetEnd = Carbon::now()->addMonth(1)->setDay(1);

    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->create(
            [
                'customer_id' => '1234',
                'budget_type_id' => 'support',
                'started_at' => $budgetStart,
                'ended_at' => $budgetEnd,
                'archived_at' => null,
                'renewal_frequency' => 'monthly',
            ]
        );

    $periods = $budget->periods();

    expect($periods)->not->toBeEmpty();
});

test('periods should not be created after budget is archived', function () {
    Event::fake();

    $budgetStart = Carbon::now()->subMonth(2)->setDay(1);
    $budgetEnd = Carbon::now()->addMonth(1)->setDay(1);
    $archivedAt = Carbon::now();

    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => $budgetStart,
            'effective_to' => $budgetEnd,
        ]))
        ->create(
            [
                'customer_id' => '1234',
                'budget_type_id' => 'support',
                'started_at' => $budgetStart,
                'ended_at' => $budgetEnd,
                'archived_at' => $archivedAt,
                'renewal_frequency' => 'monthly',
            ]
        );

    /** @var Collection $allPeriods */
    $allPeriods = $budget->periods();
    $periodsAfterArchived = $allPeriods->filter(function (Period $period) use ($archivedAt) {
        return $period->getEndDate()->greaterThanOrEqualTo($archivedAt);
    });

    expect($periodsAfterArchived)->toBeEmpty();
});
