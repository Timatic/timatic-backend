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
    foreach (['ticket_saved', 'issue_changed_to_done', 'ticket_tagged', 'calendar_event_finished'] as $id) {
        EventType::firstOrCreate(['id' => $id], ['weight' => 1]);
    }
});

test('linear activity stream', function () {
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

    expect($activities[2]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);

    if (config('timatic.feature.build_stacked_suggestions') == false) {
        return;
    }

    expect($activities[1]->entry_suggestion_id)->toEqual($activities[2]->entry_suggestion_id);
    expect($activities[3]->entry_suggestion_id)->toEqual($activities[2]->entry_suggestion_id);

    expect($activities[4]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[5]->entry_suggestion_id)->toEqual($activities[4]->entry_suggestion_id);
    expect($activities[6]->entry_suggestion_id)->toEqual($activities[4]->entry_suggestion_id);
});

test('activity stream with dangling activity', function () {
    Illuminate\Support\Facades\Event::fake();

    if (config('timatic.feature.build_stacked_suggestions') == false) {
        $this->markTestSkipped('stacked suggestions are disabled');
    }

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
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
            'ticket_number' => $this->faker->numberBetween(),
        ],
        3 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'issue_changed_to_done',
            'ticket_number' => null,
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

    expect($activities[1]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[2]->entry_suggestion_id)->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[3]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[3]->entry_suggestion_id)->not->toEqual($activities[1]->entry_suggestion_id);
});

test('outlook activities without ticket id', function () {
    if (config('timatic.feature.build_stacked_suggestions') == false) {
        $this->markTestSkipped('stacked suggestions are disabled');
    }

    Illuminate\Support\Facades\Event::fake();

    /** @var User $user */
    $user = User::factory()->create();

    $userId = $user->id;
    $customerId = $this->faker->numberBetween();
    $ticketId = $this->faker->numberBetween();
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
            'ticket_number' => $ticketId,
        ],
        3 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'calendar_event_finished',
            'ticket_number' => null,
        ],
        4 => [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'event_type_id' => 'ticket_saved',
            'ticket_number' => $ticketId,
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

    expect($activities[1]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[2]->entry_suggestion_id)->toEqual($activities[1]->entry_suggestion_id);

    expect($activities[3]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    $this->assertNotEquals($activities[2]->entry_suggestion_id, $activities[3]->entry_suggestion_id);

    expect($activities[4]->entrySuggestion)->toBeInstanceOf(EntrySuggestion::class);
    expect($activities[4]->entry_suggestion_id)->toEqual($activities[2]->entry_suggestion_id);
});
