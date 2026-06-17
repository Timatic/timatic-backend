<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Concerns\LoginUser;
use TiMacDonald\JsonApi\JsonApiResource;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('stores a budget', function () {
    $this->loginUser(permissions: ['budgets.create']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();
    $supervisor = User::factory()->create();

    $response = $this->postJson('budgets', [
        'type' => 'budgets',
        'attributes' => [
            'title' => 'test',
            'customerId' => $customer->id,
            'showToCustomer' => true,
            'initialMinutes' => $this->faker->numberBetween(100, 1000),
            'budgetTypeId' => 'project',
            'totalPrice' => $this->faker->numberBetween(100, 10000),
            'startedAt' => Carbon::today(timezone: config('timatic.preferred_timezone'))->subMonth(),
            'endedAt' => Carbon::today()->addYear(),
            'isArchived' => false,
            'supervisorUserId' => $supervisor->id,
        ],
    ])->assertCreated()
        ->assertJson([
            'data' => [
                'type' => 'budgets',
                'attributes' => [
                    'title' => 'test',
                    'customerId' => $customer->id,
                ],
            ],
        ]);

    expect(JsonApiResource::$wrap)->toEqual('data');

    /** @var Budget $budget */
    $budget = Budget::find($response->json()['data']['id']);
    $this->assertEquals($budget->supervisor_user_id, $supervisor->id);

    $this->assertDatabaseHas('budget_versions', ['budget_id' => $response->json()['data']['id']]);
});

it('does not update budget fields', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)->state([
            'title' => 'required',
            'total_price' => 100,
            'initial_minutes' => 60,
        ]))
        ->create([
            'renewal_frequency' => null,
            'started_at' => Carbon::today()->subMonths(6)->firstOfMonth(),
            'ended_at' => Carbon::today()->addMonths(6)->endOfMonth(),
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'title' => 'updated',
            'startedAt' => Carbon::today()->subMonth(),
            'endedAt' => Carbon::today()->addYear(),
            'renewalFrequency' => 'monthly',
        ],
    ])
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'type' => 'budgets',
                'attributes' => [
                    'title' => 'updated',
                    'renewalFrequency' => $budget->renewal_frequency,
                    'startedAt' => $budget->getCurrentPeriodRelationData()->getStartDate()->toJSON(),
                    'endedAt' => Carbon::today()->addYear()->toJSON(),
                ],
            ],
        ]);
});

it('can archive a budget starting before 2019', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)
            ->state([
                'effective_from' => Carbon::create(2018),
            ]))
        ->create([
            'started_at' => Carbon::create(2018),
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'isArchived' => true,
        ],
    ])->assertSuccessful()
        ->assertJson([
            'data' => [
                'attributes' => [
                    'isArchived' => true,
                ],
            ],
        ]);
});

test('it can archive budgets and set archived at', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1))
        ->create();

    $this->patchJson(
        route('budgets.update', ['budget' => $budget->id]),
        [
            'data' => [
                'type' => 'budgets',
                'attributes' => [
                    'isArchived' => true,
                ],
            ],
        ]
    )
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                'attributes' => [
                    'isArchived' => true,
                ],
            ],
        ]);

    expect(Carbon::now()->format('Y-m-d H:i'))->toEqual($budget->refresh()->archived_at->format('Y-m-d H:i'));
});

it('can update a budget with renewal frequencies', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)
            ->state([
                'effective_from' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            ]))
        ->create([
            'started_at' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            'renewal_frequency' => 'monthly',
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'effectiveFrom' => Carbon::create(2022, 6, timezone: 'Europe/Amsterdam')->utc(),
            'initialMinutes' => 2,
        ],
    ])->assertSuccessful();

    $budgetVersion = $budget->budgetVersions()->whereNull('effective_to')->first();

    expect($budgetVersion->effective_from->format('Y-m-d H:i'))->toEqual('2022-05-31 22:00');
});

it('can update a budget without explicit effective from date', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1)
            ->state([
                'effective_from' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            ]))
        ->create([
            'started_at' => Carbon::create(2022, 5, timezone: 'Europe/Amsterdam')->utc(),
            'renewal_frequency' => 'monthly',
        ]);

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'initialMinutes' => 2,
        ],
    ])->assertSuccessful();
});

it('does not create a budget version if none of its fields are posted', function () {
    $this->loginUser(permissions: ['user', 'budgets.update']);

    /** @var Budget $budget */
    $budget = Budget::factory()
        ->has(BudgetVersion::factory()->count(1))
        ->create();

    $initialBudgetVersionId = $budget->budgetVersions()->first()->id;

    $this->patchJson("budgets/{$budget->id}", [
        'type' => 'budgets',
        'attributes' => [
            'endedAt' => Carbon::now()->addYear(), // this is from the budget model only
        ],
    ])->assertSuccessful();

    expect($budget->budgetVersions()->count())->toEqual(1);
    expect($budget->budgetVersions()->first()->id)->toEqual($initialBudgetVersionId);
});

it('validates that the title is unique for a customer', function () {
    $this->loginUser(permissions: ['user', 'budgets.create']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    Budget::factory()
        ->has(BudgetVersion::factory()->state([
            'title' => 'Test',
        ]))
        ->create([
            'customer_id' => $customer->id,
        ]);

    $this->postJson('budgets', [
        'type' => 'budgets',
        'attributes' => [
            'title' => 'Test',
            'customerId' => $customer->id,
            'initialMinutes' => $this->faker->numberBetween(100, 1000),
            'budgetTypeId' => 'project',
            'startedAt' => Carbon::today()->subMonth(),
            'endedAt' => Carbon::today()->addYear(),
            'isArchived' => false,
        ],
    ])->assertUnprocessable()
        ->assertJson([
            'errors' => [
                'data.attributes.title' => ['The title of a budget should be unique.'],
            ],
        ]);
});
