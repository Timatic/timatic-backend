<?php

use App\Models\ApiToken;
use App\Models\BudgetType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

it('returns a collection response', function () {
    $this->loginUser(permissions: ['budget-types.read']);

    $budgetType = BudgetType::factory()->create();

    $this->getJson('budget-types')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                ['type' => 'budgetTypes'],
            ],
        ]);
});

it('can accept requests with api tokens', function () {
    /** @var ApiToken $token */
    $token = ApiToken::factory()->create();

    $token->givePermissionTo(['budget-types.read']);

    Auth::setUser($token);

    BudgetType::factory()->create();

    $this->getJson('budget-types')
        ->assertSuccessful();
});
