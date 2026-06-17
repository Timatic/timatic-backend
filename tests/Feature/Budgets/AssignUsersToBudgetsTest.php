<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('allows a user to be assigned when creating a budget', function () {
    $user = test()->loginUser(permissions: ['budgets.create']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    $response = postJson('budgets?include=allowedUsers', [
        'type' => 'budgets',
        'attributes' => [
            'title' => 'test',
            'customerId' => $customer->id,
            'showToCustomer' => true,
            'initialMinutes' => fake()->numberBetween(100, 1000),
            'budgetTypeId' => 'project',
            'totalPrice' => fake()->numberBetween(100, 10000),
            'startedAt' => Carbon::today()->subMonth(),
            'endedAt' => Carbon::today()->addYear(),
            'isArchived' => false,
        ],
        'relationships' => [
            'allowedUsers' => [
                'data' => [
                    [
                        'type' => 'users',
                        'id' => $user->getAuthIdentifier(),
                    ],
                ],
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'type' => 'budgets',
                'relationships' => [
                    'allowedUsers' => [
                        'data' => [
                            [
                                'id' => $user->getAuthIdentifier(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $budget = Budget::query()->find($response->json('data.id'));

    expect($budget->allowedUsers->first()->id)->toBe($user->getAuthIdentifier());
});

it('allows the allowedUser relationship to be changed when updating a budget', function () {
    $user = test()->loginUser(permissions: ['budgets.update']);

    /** @var BudgetVersion $version */
    $version = BudgetVersion::factory()->create();

    $budget = $version->budget;

    assert($budget !== null);

    expect($budget->allowedUsers->first())->toBeNull();

    patchJson('budgets/'.$budget->id.'?include=allowedUsers', [
        'type' => 'budgets',
        'attributes' => [
            'title' => 'test',
        ],
        'relationships' => [
            'allowedUsers' => [
                'data' => [
                    [
                        'type' => 'users',
                        'id' => $user->getAuthIdentifier(),
                    ],
                ],
            ],
        ],
    ])->assertSuccessful()
        ->assertJson([
            'data' => [
                'type' => 'budgets',
                'relationships' => [
                    'allowedUsers' => [
                        'data' => [
                            [
                                'id' => $user->getAuthIdentifier(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $budget->load('allowedUsers');

    expect($budget->allowedUsers->first()->id)->toBe($user->getAuthIdentifier());
});

it('returns the allowed users as include when you request budgets with allowed users included', function () {
    Budget::query()->delete();

    $user = test()->loginUser(permissions: ['budgets.read']);

    /** @var BudgetVersion $version */
    $version = BudgetVersion::factory()->create();
    $budget = $version->budget;

    $users = User::factory()->count(5)->create();

    $budget->allowedUsers()->saveMany($users);

    $response = getJson('budgets?include=allowedUsers');

    $userIds = Arr::pluck($response->json('data.0.relationships.allowedUsers.data'), 'id');

    expect($userIds)->toBe(
        $users->pluck('id')
            ->map(fn ($value) => (string) $value)->toArray()
    );
});
