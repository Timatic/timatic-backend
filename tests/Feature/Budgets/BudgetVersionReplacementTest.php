<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('refuses a version that does not start at a period boundary', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->state([
            'effective_from' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
        ]))
        ->create([
            'started_at' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            'renewal_frequency' => 'monthly',
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'effectiveFrom' => Carbon::create(2022, 6, 15, timezone: 'Europe/Amsterdam')->utc(),
            'initialMinutes' => 2,
        ],
    ])->assertUnprocessable();

    expect($budget->budgetVersions()->count())->toEqual(1);
});

it('refuses a version of a one-time budget that does not start with the budget', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->state([
            'effective_from' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
        ]))
        ->create([
            'started_at' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            'ended_at' => Carbon::create(2022, 12, 31, timezone: 'Europe/Amsterdam')->utc(),
            'renewal_frequency' => null,
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'effectiveFrom' => Carbon::create(2022, 6, timezone: 'Europe/Amsterdam')->utc(),
            'initialMinutes' => 2,
        ],
    ])->assertUnprocessable();

    expect($budget->budgetVersions()->count())->toEqual(1);
});

it('retires the versions a backdated version overlaps', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    $budget = Budget::factory()->create([
        'started_at' => Carbon::create(2022, 1, timezone: 'Europe/Amsterdam')->utc(),
        'ended_at' => Carbon::create(2022, 12, 31, timezone: 'Europe/Amsterdam')->utc(),
        'renewal_frequency' => 'monthly',
    ]);
    $january = BudgetVersion::factory()->for($budget)->create([
        'effective_from' => Carbon::create(2022, 1, timezone: 'Europe/Amsterdam')->utc(),
        'effective_to' => Carbon::create(2022, 3, timezone: 'Europe/Amsterdam')->utc()->subMinute(),
    ]);
    $march = BudgetVersion::factory()->for($budget)->create([
        'effective_from' => Carbon::create(2022, 3, timezone: 'Europe/Amsterdam')->utc(),
        'effective_to' => null,
    ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'effectiveFrom' => Carbon::create(2022, 2, timezone: 'Europe/Amsterdam')->utc(),
            'initialMinutes' => 2,
        ],
    ])->assertSuccessful();

    expect(BudgetVersion::query()->whereKey($march->id)->exists())->toBeFalse();
    expect(BudgetVersion::onlyTrashed()->whereKey($march->id)->exists())->toBeTrue();
    expect($january->fresh()->effective_to->format('Y-m-d H:i'))->toEqual('2022-01-31 22:59');
    expect($budget->budgetVersions()->whereNull('effective_to')->sole()->effective_from->format('Y-m-d H:i'))->toEqual('2022-01-31 23:00');
});
