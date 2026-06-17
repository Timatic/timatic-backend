<?php

use App\Models\BudgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

test('has renewal frequency returns value based on renewal frequencies', function () {
    $this->loginUser(permissions: ['budget-types.read']);

    BudgetType::query()->delete();
    BudgetType::factory()->create([
        'id' => 'test',
        'renewal_frequencies' => ['years', 'monthly'],
    ]);

    $response = $this->get('/budget-types');

    $response
        ->assertStatus(200)
        ->assertJson(
            fn (AssertableJson $json) => $json->has(
                'data',
                fn ($json) => $json->first(
                    fn ($json) => $json->where('id', 'test')
                        ->where('type', 'budgetTypes')
                        ->etc()
                )
            )->etc()
        );
});
