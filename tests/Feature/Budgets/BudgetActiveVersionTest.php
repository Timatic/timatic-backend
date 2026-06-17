<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('always returns an active version', function () {
    $budgetStart = CarbonImmutable::create(2022, 1, 1);
    $updatedAt = CarbonImmutable::create(2022, 2, 2);
    $budgetEnd = CarbonImmutable::create(2022, 12, 31);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'title' => 'initial',
                'effective_from' => $budgetStart,
                'effective_to' => $updatedAt,
            ])
        )
        ->has(
            BudgetVersion::factory()->count(1)->state([
                'title' => 'updated',
                'effective_from' => $updatedAt->addSecond(),
                'effective_to' => null,
            ])
        )
        ->create([
            'started_at' => $budgetStart,
            'ended_at' => $budgetEnd,
        ]);

    $this->travelTo($budgetStart->subDay());
    expect($budget->activeVersion()->title)->toEqual('initial');

    $this->travelTo($updatedAt->subDay());
    expect($budget->activeVersion()->title)->toEqual('initial');

    $this->travelTo($updatedAt->addDay());
    expect($budget->activeVersion()->title)->toEqual('updated');

    $this->travelTo($budgetEnd->addDay());
    expect($budget->activeVersion()->title)->toEqual('updated');
});
