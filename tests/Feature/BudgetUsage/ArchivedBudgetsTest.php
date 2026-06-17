<?php

use App\DataTransferObjects\BudgetMutation;
use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Services\BudgetUsageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('budget usage service should ignore archived budgets', function () {
    Budget::query()->delete();

    Event::fake();

    createArchivedBudget(start: Carbon::now()->startOfMonth()->subMonths(2), end: Carbon::now()->startOfMonth()->addMonth(), archivedAt: Carbon::now()->startOfMonth());

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    expect($budgetMutations)->toBeEmpty();
});

test('budget usage service has expire credit for archived budgets', function () {
    Event::fake();

    createArchivedBudget(start: Carbon::now()->subMonths(2)->setDay(1), end: Carbon::now()->addMonth()->setDay(1), archivedAt: Carbon::now()->setDay(3));

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->last();

    expect($budgetMutations)->not->toBeEmpty();
    expect($budgetMutation->expiredCredit->toInt())->toEqual(100);
});

test('budget usage service has expire credit for budgets with ended at', function () {
    Event::fake();

    createArchivedBudget(start: Carbon::now()->setDay(1)->subMonths(2), end: Carbon::now());

    /** @var BudgetUsageService $usageService */
    $usageService = resolve(BudgetUsageService::class);
    $budgetMutations = $usageService->get(Carbon::now());

    /** @var BudgetMutation $budgetMutation */
    $budgetMutation = $budgetMutations->last();

    expect($budgetMutations)->not->toBeEmpty();
    expect($budgetMutation->expiredCredit->toInt())->toEqual(100);
});

function createArchivedBudget(Carbon $start, Carbon $end, ?Carbon $archivedAt = null): void
{
    Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'total_price' => 100,
            'initial_minutes' => 60,
            'effective_from' => $start,
            'effective_to' => $end,
        ]))
        ->create(
            [
                'budget_type_id' => 'support',
                'started_at' => $start,
                'ended_at' => $end,
                'archived_at' => $archivedAt,
                'renewal_frequency' => 'monthly',
            ]
        );
}
