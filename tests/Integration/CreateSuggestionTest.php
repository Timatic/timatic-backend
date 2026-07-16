<?php

use App\Events\ActivityCreated;
use App\Listeners\CreateSuggestion;
use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    config()->set('timatic.feature.build_stacked_suggestions', true);

    foreach (['ticket_saved', 'issue_changed_to_done', 'ticket_tagged', 'calendar_event_finished'] as $id) {
        EventType::firstOrCreate(['id' => $id], ['weight' => 1]);
    }
});

test('linear activity stream bundles strictly per ticket', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
    $ticketId1 = $this->faker->numberBetween();
    $ticketId2 = $this->faker->numberBetween();

    $data = [
        1 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'issue_changed_to_done',
            'ticket_number' => null,
        ],
        2 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId1,
        ],
        3 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId1,
        ],
        4 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_tagged',
            'ticket_number' => null,
        ],
        5 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'calendar_event_finished',
            'ticket_number' => $ticketId2,
        ],
        6 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId2,
        ],
    ];
    foreach ($data as $key => $d) {
        /** @var Activity[] $activities */
        $activities[$key] = Activity::factory()
            ->has(
                Event::factory()->state($d)
            )->create($d);
    }

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);

    foreach ($activities as $activity) {
        $listener->handle(new ActivityCreated($activity));
    }

    foreach ($activities as $activity) {
        $activity->refresh();
    }

    expect($activities[1]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[4]->entry_suggestion_id)->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[2]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[3]->entry_suggestion_id)->toEqual($activities[2]->entry_suggestion_id);
    expect($activities[2]->entry_suggestion_id)->not->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[5]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[6]->entry_suggestion_id)->toEqual($activities[5]->entry_suggestion_id);
    expect($activities[5]->entry_suggestion_id)->not->toEqual($activities[2]->entry_suggestion_id);
});

test('rejected suggestion is not reused for later activities', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
    $ticketId = $this->faker->numberBetween();
    $state = [
        'user_id' => $userId,
        'customer_id' => $customerId,
        'event_type_id' => 'ticket_saved',
        'ticket_number' => $ticketId,
    ];

    /** @var Activity $first */
    $first = Activity::factory()->has(Event::factory()->state($state))->create($state);

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);
    $listener->handle(new ActivityCreated($first));

    $first->refresh();
    $first->entrySuggestion?->delete();

    /** @var Activity $second */
    $second = Activity::factory()->has(Event::factory()->state($state))->create($state);
    $listener->handle(new ActivityCreated($second));

    $second->refresh();
    expect($second->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($second->entry_suggestion_id)->not->toEqual($first->entry_suggestion_id);
});

test('flag off keeps one suggestion per activity', function () {
    Illuminate\Support\Facades\Event::fake();
    config()->set('timatic.feature.build_stacked_suggestions', false);

    /** @var User $user */
    $user = User::factory()->create();

    $state = [
        'user_id' => $user->id,
        'customer_id' => $this->faker->numberBetween(),
        'event_type_id' => 'ticket_saved',
        'ticket_number' => $this->faker->numberBetween(),
    ];

    /** @var Activity $first */
    $first = Activity::factory()->has(Event::factory()->state($state))->create($state);
    /** @var Activity $second */
    $second = Activity::factory()->has(Event::factory()->state($state))->create($state);

    /** @var CreateSuggestion $listener */
    $listener = app(CreateSuggestion::class);
    $listener->handle(new ActivityCreated($first));
    $listener->handle(new ActivityCreated($second));

    $first->refresh();
    $second->refresh();

    expect(EntrySuggestion::count())->toBe(2);
    expect($first->entry_suggestion_id)->not->toEqual($second->entry_suggestion_id);
});
