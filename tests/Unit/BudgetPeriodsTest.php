<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('a budget starting on the first of the month starts each period on the first across the winter to summer DST transition', function () {
    $budgetStart = Carbon::create(2021, 12, 31, 23, 00);
    $budgetEnd = Carbon::create(2023, 6, 1);

    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    $this->travelTo(Carbon::create(2023, 04, 01));

    $periods = $budget->periods();

    $period = $periods->get(2);
    expect($period->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 03, 01, timezone: 'Europe/Amsterdam'));

    $period = $periods->get(3);
    expect($period->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 04, 01, timezone: 'Europe/Amsterdam'));
});

test('the last period of a budget with a monthly renewal frequency should end when the budget ends', function () {
    $budgetStart = Carbon::create(2022, 1, 1);
    $budgetEnd = Carbon::create(2022, 3, 15);

    // the endDate should not match the renewal frequency
    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    expect($budget->periods()->last()->getEndDate())->toEqual($budgetEnd);
});

test('a budget starting on the first of the month in summertime starts each period on the first across DST transitions', function () {
    $budgetStart = Carbon::create(2021, 5, 31, 22, 00);
    $budgetEnd = Carbon::create(2023, 6, 1);

    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    $this->travelTo(Carbon::create(2023, 04, 01));

    $periods = $budget->periods();

    $februaryPeriod = $periods->get(8);
    expect($februaryPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 02, 01, timezone: 'Europe/Amsterdam'));

    $marchPeriod = $periods->get(9);
    expect($marchPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 03, 01, timezone: 'Europe/Amsterdam'));

    $aprilPeriod = $periods->get(10);
    expect($aprilPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 04, 01, timezone: 'Europe/Amsterdam'));

    $mayPeriod = $periods->get(11);
    expect($mayPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 05, 01, timezone: 'Europe/Amsterdam'));
});

test('a mid-month budget has a partial first period and subsequent periods start at 00:00 after the winter to summer DST transition', function () {
    $budgetStart = Carbon::create(2022, 03, 10, 23);
    $budgetEnd = Carbon::create(2023, 6, 1);

    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    $this->travelTo(Carbon::create(2023, 04, 01));

    $periods = $budget->periods();

    $marchPeriod = $periods->get(0);
    expect($marchPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 03, 11, 00, timezone: 'Europe/Amsterdam'));

    $aprilPeriod = $periods->get(1);
    expect($aprilPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 04, 01, timezone: 'Europe/Amsterdam'));

    $mayPeriod = $periods->get(2);
    expect($mayPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 05, 01, timezone: 'Europe/Amsterdam'));
});

test('a mid-month budget has a partial first period and all subsequent periods start on the first of the month', function () {
    $budgetStart = Carbon::create(2022, 1, 15, 0, 0, 0, 'Europe/Amsterdam');
    $budgetEnd = Carbon::create(2022, 4, 1, 0, 0, 0, 'Europe/Amsterdam');

    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    $this->travelTo(Carbon::create(2022, 4, 1));

    $periods = $budget->periods();

    $januaryPeriod = $periods->get(0);
    expect($januaryPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 1, 15, 0, timezone: 'Europe/Amsterdam'));
    expect($januaryPeriod->getEndDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 1, 31, 23, 59, 59, timezone: 'Europe/Amsterdam'));

    $februaryPeriod = $periods->get(1);
    expect($februaryPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 2, 1, 0, timezone: 'Europe/Amsterdam'));
    expect($februaryPeriod->getEndDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 2, 28, 23, 59, 59, timezone: 'Europe/Amsterdam'));

    $marchPeriod = $periods->get(2);
    expect($marchPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 3, 1, 0, timezone: 'Europe/Amsterdam'));
    expect($marchPeriod->getEndDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 3, 31, 23, 59, 59, timezone: 'Europe/Amsterdam'));
});

test('a mid-month budget with legacy period alignment keeps periods on the same day of month', function () {
    config()->set('timatic.feature.align_periods_to_month_start', false);

    $budgetStart = Carbon::create(2022, 1, 15, 0, 0, 0, 'Europe/Amsterdam');
    $budgetEnd = Carbon::create(2022, 4, 1, 0, 0, 0, 'Europe/Amsterdam');

    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'effective_from' => $budgetStart,
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
            'renewal_frequency' => 'month',
        ]);

    $this->travelTo(Carbon::create(2022, 4, 1));

    $periods = $budget->periods();

    $januaryPeriod = $periods->get(0);
    expect($januaryPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 1, 15, 0, timezone: 'Europe/Amsterdam'));
    expect($januaryPeriod->getEndDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 2, 14, 23, 59, 59, timezone: 'Europe/Amsterdam'));

    $februaryPeriod = $periods->get(1);
    expect($februaryPeriod->getStartDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 2, 15, 0, timezone: 'Europe/Amsterdam'));
    expect($februaryPeriod->getEndDate()->timezone('Europe/Amsterdam'))->toEqual(Carbon::create(2022, 3, 14, 23, 59, 59, timezone: 'Europe/Amsterdam'));
});
