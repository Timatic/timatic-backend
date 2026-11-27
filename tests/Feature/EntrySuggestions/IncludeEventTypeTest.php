<?php

use App\Models\Activity;
use App\Models\Budget;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

it('includes the name of the event type of the events an activity was built from', function () {
    /** @var User $user */
    $user = $this->loginUser(permissions: ['entry-suggestions.read']);

    $eventType = EventType::factory()->create([
        'id' => 'pull_request_reviewed',
        'name' => 'Pull request reviewed',
    ]);

    $entrySuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'budget_id' => Budget::factory(),
    ]);

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventType->id,
    ]);

    Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $entrySuggestion->id,
    ])->events()->attach($event);

    $this->getJson('entry-suggestions?include=activities.events.eventType')
        ->assertSuccessful()
        ->assertJson([
            'included' => [
                [
                    'type' => 'activities',
                ],
                [
                    'type' => 'events',
                    'id' => (string) $event->id,
                    'relationships' => [
                        'eventType' => [
                            'data' => [
                                'type' => 'eventTypes',
                                'id' => 'pull_request_reviewed',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'eventTypes',
                    'id' => 'pull_request_reviewed',
                    'attributes' => [
                        'name' => 'Pull request reviewed',
                    ],
                ],
            ],
        ]);
});

it('includes the event type names on a single suggestion', function () {
    /** @var User $user */
    $user = $this->loginUser(permissions: ['entry-suggestions.read']);

    $eventType = EventType::factory()->create([
        'id' => 'pull_request_reviewed',
        'name' => 'Pull request reviewed',
    ]);

    $entrySuggestion = EntrySuggestion::factory()->create([
        'user_id' => $user->id,
        'budget_id' => Budget::factory(),
    ]);

    $event = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventType->id,
    ]);

    Activity::factory()->create([
        'user_id' => $user->id,
        'entry_suggestion_id' => $entrySuggestion->id,
    ])->events()->attach($event);

    $this->getJson("entry-suggestions/{$entrySuggestion->id}?include=activities.events.eventType")
        ->assertSuccessful()
        ->assertJson([
            'included' => [
                [
                    'type' => 'activities',
                ],
                [
                    'type' => 'events',
                    'id' => (string) $event->id,
                    'relationships' => [
                        'eventType' => [
                            'data' => [
                                'type' => 'eventTypes',
                                'id' => 'pull_request_reviewed',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'eventTypes',
                    'id' => 'pull_request_reviewed',
                    'attributes' => [
                        'name' => 'Pull request reviewed',
                    ],
                ],
            ],
        ]);
});
