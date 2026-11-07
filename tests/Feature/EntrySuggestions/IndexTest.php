<?php

use App\Models\Activity;
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

it('returns the minutes spent on the activities of a suggestion', function () {
    /** @var User $user */
    $user = $this->loginUser(permissions: ['entry-suggestions.read']);

    $entrySuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'budget_id' => Budget::factory(),
    ]);

    Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $entrySuggestion->id,
        'started_at' => '2026-11-02 09:00:00',
        'ended_at' => '2026-11-02 09:25:00',
    ]);

    $this->getJson('entry-suggestions')
        ->assertSuccessful()
        ->assertJson([
            'data' => [
                [
                    'attributes' => [
                        'minutesSpent' => 25,
                    ],
                ],
            ],
        ]);
});
