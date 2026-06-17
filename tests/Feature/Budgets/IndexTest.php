<?php

use App\Models\Budget;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\User;
use App\Services\BudgetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns a collection response', function () {
    $this->loginUser(permissions: ['budgets.read']);

    /** @var Customer $customer */
    $customer = Customer::factory()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $attributes = [
        'title' => 'test',
        'customer_id' => $customer->id,
        'initial_minutes' => 1000,
        'budget_type_id' => 'support',
        'total_price' => '1000.95',
        'started_at' => '2021-01-01',
        'ended_at' => '2021-05-31',
        'archived_at' => null,
        'renewal_frequency' => 'monthly',
        'supervisor_user_id' => $user->id,
    ];

    $budget = Budget::query()->create($attributes);

    resolve(BudgetVersionService::class)->createAndReplaceVersion(
        $budget,
        $attributes
    );

    $this->get('budgets')->assertSuccessful();

    expect(JsonApiResource::$wrap)->toEqual('data');
});

it('returns budgets filtered on customer by external id', function () {
    $this->loginUser(permissions: ['budgets.read']);

    BudgetVersion::factory()->create();

    $budget = BudgetVersion::factory()->create()->budget;

    $customer = $budget->customer;

    $response = $this->get('/budgets?filter[customerExternalId]='.$customer->external_id)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    expect((string) $response->json('data.0.attributes.customerId'))->toEqual((string) $customer->id);
});

it('returns budgets filtered on show to customer', function () {
    $this->loginUser(permissions: ['budgets.read']);

    $budget1 = BudgetVersion::factory()->create()->budget;

    $budget2 = BudgetVersion::factory()->create()->budget;
    $budget2->show_to_customer = false;
    $budget2->save();

    $this->getJson('/budgets?filter[showToCustomer]=true')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJson([
            'data' => [
                [
                    'id' => (string) $budget1->id,
                    'type' => 'budgets',
                ],
            ],
        ])
        ->assertJsonMissing([
            'data' => [
                [
                    'id' => (string) $budget2->id,
                    'type' => 'budgets',
                ],
            ],
        ]);
});
