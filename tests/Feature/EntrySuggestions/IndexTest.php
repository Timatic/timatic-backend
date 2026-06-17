<?php

use App\Models\Budget;
use App\Models\EntrySuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('returns a collection response', function () {
    /** @var User $user */
    $user = $this->loginUser(permissions: ['entry-suggestions.read']);

    $budget = Budget::factory()->create();

    $entrySuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
    ]
    );

    $this->getJson('entry-suggestions')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                [
                    'type' => 'entrySuggestions',
                    'attributes' => [
                        'budgetId' => $budget->id,
                    ],
                ],
            ],
        ]);
});
