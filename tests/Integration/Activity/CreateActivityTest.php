<?php

use App\Events\EventCreated;
use App\Listeners\CreateActivity;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(RefreshDatabase::class);

uses(WithFaker::class);

test('if event has no start then activity should start 15 minutes before', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var Event $event */
    $event = Event::factory()->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    $listener->handle(new EventCreated($event));

    $event->load('activity');
    expect($event->activity()->exists())->toBeTrue();
    expect($event->activity?->events?->isNotEmpty())->toBeTrue();
    expect($event->ended_at->subMinutes(15))->toEqual($event->activity?->started_at);
});

test('if event has start and end then activity should be same period', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var Event $event */
    $event = Event::factory()->create([
        'started_at' => Carbon::now()->subWeeks(2),
    ]);

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    $listener->handle(new EventCreated($event));

    expect($event->activity)->not->toBeNull();
    expect($event->activity->events->isNotEmpty())->toBeTrue();
    expect($event->started_at)->toEqual($event->activity->started_at);
    expect($event->ended_at)->toEqual($event->activity->ended_at);
});

test('created activity does not overlap existing one', function () {
    Illuminate\Support\Facades\Event::fake();

    $overlappingActivity = Activity::factory()->create();

    /** @var Activity $overlappingActivity */
    $overlappingEvent = Event::factory()->state([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 10, second: 0),
    ])->create();
    $overlappingActivity->events()->save($overlappingEvent);

    /** @var Event $event */
    $event = Event::factory()->state([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 5, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
    ])->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    $listener->handle(new EventCreated($event));

    expect($overlappingEvent->ended_at)->toBeGreaterThanOrEqual($event->activity->started_at);
});

test('if two events overlap then one with highest weight should become activity', function () {
    if (config('timatic.feature.activity_overlap_detection') == false) {
        $this->markTestSkipped('overlap detection feature is disabled');
    }

    Illuminate\Support\Facades\Event::fake();
    $eventTypeLight = EventType::factory()->state([
        'weight' => 1,
    ])->create();
    $eventTypeHeavy = EventType::factory()->state([
        'weight' => 999,
    ])->create();

    $events = [];

    /** @var Event $overlappingEvent */
    $events[0] = Event::factory()->state([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 10, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
        'event_type_id' => $eventTypeLight->id,
    ])->create();

    /** @var Event $event */
    $events[1] = Event::factory()->state([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 5, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'event_type_id' => $eventTypeHeavy->id,
    ])->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    foreach ($events as $event) {
        $listener->handle(new EventCreated($event));
    }

    // should return 2 activities
    expect($events[0]->activity)->toBeInstanceOf(Activity::class);
    expect($events[1]->activity)->toBeInstanceOf(Activity::class);

    // $events[1] should be the main activity because of its higher weight
    expect($events[1]->activity->startedAt)->toEqual($events[1]->started_at);
    expect($events[1]->activity->endedAt)->toEqual($events[1]->ended_at);

    // $events[0] should start after $event[1] for the remainder of its duration that does NOT overlap
    expect($events[1]->ended_at)->toEqual($events[0]->activity->startedAt);
    expect($events[0]->activity->endedAt)->toEqual($events[0]->ended_at);
});

test('if event fits in previous activity add it', function () {
    Illuminate\Support\Facades\Event::fake();

    Source::firstOrCreate(['id' => Source::ID_TOPDESK], ['title' => 'Topdesk']);

    $sameState = [
        'event_type_id' => EventType::factory()->create()->id,
        'customer_id' => $this->faker->word(),
        'ticket_number' => $this->faker->word(),
        'user_id' => User::factory()->create()->id,
        'source_id' => Source::ID_TOPDESK,
    ];

    /** @var Activity $previousActivity */
    $previousActivity = Activity::factory()->state(array_merge([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 10, second: 0),
    ], $sameState))->create();

    /** @var Event $event */
    $event = Event::factory()->state(array_merge([
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
    ], $sameState))->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    $listener->handle(new EventCreated($event));

    $previousEvents = $previousActivity->events->map(function (Event $event) {
        return $event->id;
    });
    $previousActivity->refresh();

    expect($previousEvents)->toContain($event->id);
    expect($previousActivity->ended_at)->toEqual($event->ended_at);
});

test('activity should only contain events from one customer', function () {
    Illuminate\Support\Facades\Event::fake();

    /** @var Event[] $events */
    $events = [];

    $sameState = [
        'event_type_id' => EventType::factory()->createOne()->id,
        'ticket_number' => $this->faker->word(),
        'user_id' => User::factory()->create()->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
        'customer_id' => 'customerX',
    ]))->create();

    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
        'customer_id' => 'customerY',
    ]))->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    foreach ($events as $event) {
        $listener->handle(new EventCreated($event));
    }

    expect($events[0]->activity->customer_id)->toEqual($events[0]->customer_id);
    expect($events[1]->activity->customer_id)->toEqual($events[1]->customer_id);
    $this->assertNotEquals($events[0]->activity->customer_id, $events[1]->activity->customer_id);
});

test('events without customer should not be combined', function () {
    Illuminate\Support\Facades\Event::fake();

    $events = [];
    $eventTypeId = EventType::factory()->createOne()->id;

    Source::firstOrCreate(['id' => Source::ID_OUTLOOK_CALENDAR], ['title' => 'Outlook Calendar']);

    $sameState = [
        'event_type_id' => $eventTypeId,
        'customer_id' => null,
        'ticket_number' => null,
        'source_id' => 'outlook_calendar',
        'user_id' => User::factory()->create()->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
    ]))->create();
    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
    ]))->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    foreach ($events as $event) {
        $listener->handle(new EventCreated($event));
    }

    expect(Activity::query()->count())->toEqual(2);
});

test('events without ticket should not be combined', function () {
    Illuminate\Support\Facades\Event::fake();

    $events = [];
    $eventTypeId = EventType::factory()->createOne()->id;

    Source::firstOrCreate(['id' => Source::ID_OUTLOOK_CALENDAR], ['title' => 'Outlook Calendar']);

    $sameState = [
        'event_type_id' => $eventTypeId,
        'customer_id' => $this->faker->word(),
        'ticket_number' => null,
        'source_id' => 'outlook_calendar',
        'user_id' => User::factory()->create()->id,
    ];

    $events[0] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
    ]))->create();
    $events[1] = Event::factory()->state(array_merge($sameState, [
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 15, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 0, minute: 20, second: 0),
    ]))->create();

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);

    foreach ($events as $event) {
        $listener->handle(new EventCreated($event));
    }

    expect(Activity::query()->count())->toEqual(2);
});

test('a fully covered lower-weight activity is absorbed instead of getting a negative duration', function () {
    config()->set('timatic.feature.activity_overlap_detection', true);
    Illuminate\Support\Facades\Event::fake();

    $eventTypeLight = EventType::factory()->state(['weight' => 1])->create();
    $eventTypeHeavy = EventType::factory()->state(['weight' => 999])->create();
    $user = User::factory()->create();

    /** @var Event $coveredEvent */
    $coveredEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeLight->id,
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 5, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 10, second: 0),
    ]);

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);
    $listener->handle(new EventCreated($coveredEvent));

    /** @var Event $coveringEvent */
    $coveringEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeHeavy->id,
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 15, second: 0),
    ]);
    $listener->handle(new EventCreated($coveringEvent));

    expect(Activity::count())->toBe(1)
        ->and(Activity::whereColumn('started_at', '>=', 'ended_at')->count())->toBe(0)
        ->and($coveredEvent->fresh()->activity_id)->toBe($coveringEvent->fresh()->activity_id);
});

test('an event fully covered by a higher-weight activity attaches to that activity', function () {
    config()->set('timatic.feature.activity_overlap_detection', true);
    Illuminate\Support\Facades\Event::fake();

    $eventTypeLight = EventType::factory()->state(['weight' => 1])->create();
    $eventTypeHeavy = EventType::factory()->state(['weight' => 999])->create();
    $user = User::factory()->create();

    /** @var Event $meetingEvent */
    $meetingEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeHeavy->id,
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 0, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 30, second: 0),
    ]);

    /** @var CreateActivity $listener */
    $listener = app(CreateActivity::class);
    $listener->handle(new EventCreated($meetingEvent));

    /** @var Event $coveredEvent */
    $coveredEvent = Event::factory()->create([
        'user_id' => $user->id,
        'event_type_id' => $eventTypeLight->id,
        'started_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 5, second: 0),
        'ended_at' => Carbon::now()->subWeek()->setTime(hour: 10, minute: 10, second: 0),
    ]);
    $listener->handle(new EventCreated($coveredEvent));

    expect(Activity::count())->toBe(1)
        ->and($coveredEvent->fresh()->activity_id)->toBe($meetingEvent->fresh()->activity_id);
});

it('loads the event type of an activity', function () {
    Illuminate\Support\Facades\Event::fake();
    $eventType = EventType::firstOrCreate(['id' => 'ticket_saved'], ['weight' => 1]);

    $activity = Activity::factory()->create(['event_type_id' => $eventType->id]);

    expect($activity->eventType)->toBeInstanceOf(EventType::class)
        ->and($activity->eventType->id)->toBe('ticket_saved');
});
