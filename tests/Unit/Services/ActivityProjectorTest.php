<?php

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Entry;
use App\Models\Event;
use App\Models\EventType;
use App\Services\ActivityProjector;
use Carbon\Carbon;

test('an event without start becomes an activity starting 15 minutes before its end', function () {
    $event = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$event]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->events->all())->toBe([$event]);
});

test('an event with start and end becomes an activity of the same period', function () {
    $event = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$event]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'));
});

test('same-ticket events within the chain gap merge into one activity across sources', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'source_id' => 'bitbucket',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'source_id' => 'jira',
        'started_at' => Carbon::parse('2026-07-16 09:40'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $second->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$first, $second]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->events)->toHaveCount(2);
});

test('same-ticket events further apart than the chain gap become separate activities', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:46'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $second->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$first, $second]), collect());

    expect($activities)->toHaveCount(2);
});

test('events of different customers never merge', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $second->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$first, $second]), collect());

    expect($activities)->toHaveCount(2)
        ->and($activities->pluck('customer_id')->sort()->values()->all())->toBe(['customerX', 'customerY']);
});

test('events without customer are never combined', function () {
    $eventType = new EventType(['id' => 'calendar_event_started', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => null,
        'ticket_number' => null,
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:15'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => null,
        'ticket_number' => null,
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:15'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $second->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$first, $second]), collect());

    expect($activities)->toHaveCount(2);
});

test('a null-ticket event glues onto the preceding group of the same customer even with another event type', function () {
    $ticketed = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $ticketed->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:50'),
    ]);
    $ticketless->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$ticketed, $ticketless]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->ticket_number)->toBe('TIC-1')
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:50'))
        ->and($activities[0]->events)->toHaveCount(2);
});

test('a null-ticket event without preceding group is claimed by a following group of the same customer', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:20'),
    ]);
    $ticketless->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 1]));
    $ticketed = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $ticketed->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$ticketless, $ticketed]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->ticket_number)->toBe('TIC-1')
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'));
});

test('a null-ticket event between two groups goes to the preceding one', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $preceding = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $preceding->setRelation('eventType', $eventType);
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $ticketless->setRelation('eventType', $eventType);
    $following = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:50'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $following->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$preceding, $ticketless, $following]), collect());

    $ticketOne = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-1');
    expect($activities)->toHaveCount(2)
        ->and($ticketOne->events)->toHaveCount(2);
});

test('a null-ticket event of another customer is not glued', function () {
    $ticketed = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $ticketed->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => null,
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $ticketless->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$ticketed, $ticketless]), collect());

    expect($activities)->toHaveCount(2);
});

test('within one customer the higher-weight group keeps its period and the lower one is trimmed', function () {
    $light = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 00:10'),
        'ended_at' => Carbon::parse('2026-07-16 00:20'),
    ]);
    $light->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 99]));
    $heavy = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 00:05'),
        'ended_at' => Carbon::parse('2026-07-16 00:15'),
    ]);
    $heavy->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$light, $heavy]), collect());

    $heavyActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-2');
    $lightActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-1');
    expect($activities)->toHaveCount(2)
        ->and($heavyActivity->started_at)->toEqual(Carbon::parse('2026-07-16 00:05'))
        ->and($heavyActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 00:15'))
        ->and($lightActivity->started_at)->toEqual(Carbon::parse('2026-07-16 00:15'))
        ->and($lightActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 00:20'));
});

test('a group fully covered by a dominant group of the same customer hands its events to that group', function () {
    $covering = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $covering->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 1]));
    $covered = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:05'),
        'ended_at' => Carbon::parse('2026-07-16 10:10'),
    ]);
    $covered->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 99]));

    $activities = (new ActivityProjector)->project(collect([$covering, $covered]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->events->all())->toBe([$covering, $covered])
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:30'));
});

test('a browser event covered by work for another customer stays unattached instead of mixing customers', function () {
    $covering = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $covering->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 1]));
    $covered = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:05'),
        'ended_at' => Carbon::parse('2026-07-16 10:10'),
    ]);
    $covered->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 100]));

    $activities = (new ActivityProjector)->project(collect([$covering, $covered]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->events)->toHaveCount(1)
        ->and($activities[0]->events->first())->toBe($covering);
});

test('within one customer on equal weight the earlier-starting group is dominant', function () {
    $earlier = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $earlier->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 5]));
    $later = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $later->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 5]));

    $activities = (new ActivityProjector)->project(collect([$earlier, $later]), collect());

    $earlierActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-1');
    $laterActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-2');
    expect($earlierActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($laterActivity->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'));
});

test('within one customer a subordinate group starts after the latest dominant overlapping group', function () {
    $heavyType = new EventType(['id' => 'calendar_event_started', 'weight' => 1]);
    $firstDominant = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $firstDominant->setRelation('eventType', $heavyType);
    $secondDominant = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:20'),
    ]);
    $secondDominant->setRelation('eventType', $heavyType);
    $subordinate = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-3',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:15'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $subordinate->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 99]));

    $activities = (new ActivityProjector)->project(collect([$firstDominant, $secondDominant, $subordinate]), collect());

    $subordinateActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-3');
    expect($subordinateActivity->started_at)->toEqual(Carbon::parse('2026-07-16 10:30'));
});

test('shuffled input produces the same activities as chronological input', function () {
    $makeEvents = function () {
        $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
        $events = [];
        foreach ([['09:00', '09:20', 'TIC-1'], ['09:25', '09:40', 'TIC-1'], ['10:30', '11:00', 'TIC-2']] as [$start, $end, $ticket]) {
            $event = new Event([
                'user_id' => 1,
                'customer_id' => 'customerX',
                'ticket_number' => $ticket,
                'event_type_id' => 'commit_created',
                'started_at' => Carbon::parse("2026-07-16 $start"),
                'ended_at' => Carbon::parse("2026-07-16 $end"),
            ]);
            $event->setRelation('eventType', $eventType);
            $events[] = $event;
        }

        return $events;
    };

    $chronological = (new ActivityProjector)->project(collect($makeEvents()), collect());
    $shuffled = (new ActivityProjector)->project(collect(array_reverse($makeEvents())), collect());

    $signature = fn ($activities) => $activities
        ->map(fn ($activity) => $activity->ticket_number.'|'.$activity->started_at.'|'.$activity->ended_at.'|'.$activity->events->count())
        ->sort()->values()->all();
    expect($signature($shuffled))->toBe($signature($chronological));
});

test('an activity partially overlapping an entry period is trimmed to the unbooked part', function () {
    $event = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));
    $entry = new Entry(['started_at' => Carbon::parse('2026-07-16 09:00'), 'ended_at' => Carbon::parse('2026-07-16 10:00')]);

    $activities = (new ActivityProjector)->project(collect([$event]), collect([$entry]));

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 12:00'));
});

test('an entry period inside an activity splits it into two activities', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $morning = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:50'),
    ]);
    $morning->setRelation('eventType', $eventType);
    $noon = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $noon->setRelation('eventType', $eventType);
    $entry = new Entry(['started_at' => Carbon::parse('2026-07-16 09:45'), 'ended_at' => Carbon::parse('2026-07-16 10:15')]);

    $activities = (new ActivityProjector)->project(collect([$morning, $noon]), collect([$entry]));

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
        ->and($activities[0]->events)->not->toBeEmpty()
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 10:15'))
        ->and($activities[1]->events)->not->toBeEmpty();
});

test('an activity fully inside entry periods is not created', function () {
    $event = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));
    $entry = new Entry(['started_at' => Carbon::parse('2026-07-16 09:00'), 'ended_at' => Carbon::parse('2026-07-16 11:00')]);

    $activities = (new ActivityProjector)->project(collect([$event]), collect([$entry]));

    expect($activities)->toBeEmpty();
});

test('a meeting spanning two commits is split into three activities around the commits', function () {
    $commitType = new EventType(['id' => 'commit_created', 'weight' => 1]);
    $meetingType = new EventType(['id' => 'calendar_event_started', 'weight' => 100]);

    $commit1 = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:10'),
        'ended_at' => Carbon::parse('2026-07-16 10:25'),
    ]);
    $commit1->setRelation('eventType', $commitType);

    $commit2 = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 11:00'),
        'ended_at' => Carbon::parse('2026-07-16 11:15'),
    ]);
    $commit2->setRelation('eventType', $commitType);

    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $meeting->setRelation('eventType', $meetingType);

    $activities = (new ActivityProjector)->project(collect([$commit1, $commit2, $meeting]), collect());

    $commitActivities = $activities->filter(fn ($a) => $a->ticket_number === 'TIC-1')
        ->sortBy('started_at')->values();
    $meetingActivities = $activities->filter(fn ($a) => $a->ticket_number === 'TIC-2')
        ->sortBy('started_at')->values();

    expect($activities)->toHaveCount(5)
        ->and($commitActivities)->toHaveCount(2)
        ->and($commitActivities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:10'))
        ->and($commitActivities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:25'))
        ->and($commitActivities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($commitActivities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 11:15'))
        ->and($meetingActivities)->toHaveCount(3)
        ->and($meetingActivities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($meetingActivities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:10'))
        ->and($meetingActivities[0]->events->contains($meeting))->toBeTrue()
        ->and($meetingActivities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 10:25'))
        ->and($meetingActivities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($meetingActivities[1]->events->contains($meeting))->toBeTrue()
        ->and($meetingActivities[2]->started_at)->toEqual(Carbon::parse('2026-07-16 11:15'))
        ->and($meetingActivities[2]->ended_at)->toEqual(Carbon::parse('2026-07-16 12:00'))
        ->and($meetingActivities[2]->events->contains($meeting))->toBeTrue();
});

test('fast sequential point events of equal weight each get their own time slice', function () {
    $eventType = new EventType(['id' => 'commit_created', 'weight' => 1]);

    $commit1 = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $commit1->setRelation('eventType', $eventType);

    $commit2 = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 10:05'),
    ]);
    $commit2->setRelation('eventType', $eventType);

    $commit3 = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 10:10'),
    ]);
    $commit3->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$commit1, $commit2, $commit3]), collect());

    $tic1 = $activities->filter(fn ($a) => $a->ticket_number === 'TIC-1')
        ->sortBy('started_at')->values();
    $tic2 = $activities->filter(fn ($a) => $a->ticket_number === 'TIC-2')
        ->sortBy('started_at')->values();

    expect($activities)
        ->and($tic1)->toHaveCount(2)
        ->and($tic1[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
        ->and($tic1[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and((int) $tic1[0]->started_at->diffInMinutes($tic1[0]->ended_at))->toBe(15)
        ->and($tic1[1]->started_at)->toEqual(Carbon::parse('2026-07-16 10:05'))
        ->and($tic1[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:10'))
        ->and((int) $tic1[1]->started_at->diffInMinutes($tic1[1]->ended_at))->toBe(5)
        ->and($tic2)->toHaveCount(1)
        ->and($tic2[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($tic2[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:05'))
        ->and((int) $tic2[0]->started_at->diffInMinutes($tic2[0]->ended_at))->toBe(5);
});

test('within one customer a high-weight event inside a low-weight event splits the low-weight into two activities', function () {
    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 99]));
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$meeting, $commit]), collect());

    $meetingActivities = $activities->filter(fn ($a) => $a->ticket_number === 'TIC-1')->values();
    $commitActivity = $activities->first(fn ($a) => $a->ticket_number === 'TIC-2');
    expect($activities)->toHaveCount(3)
        ->and($meetingActivities)->toHaveCount(2)
        ->and($meetingActivities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($meetingActivities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:30'))
        ->and($meetingActivities[0]->events->contains($meeting))->toBeTrue()
        ->and($commitActivity->started_at)->toEqual(Carbon::parse('2026-07-16 09:30'))
        ->and($commitActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
        ->and($meetingActivities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
        ->and($meetingActivities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($meetingActivities[1]->events->contains($meeting))->toBeTrue();
});

test('a customerless event is booked like the previous entry of its event sequence', function () {
    $meeting = new Event([
        'user_id' => 1,
        'source_id' => 'google_calendar',
        'external_id' => 'weekly-sync',
        'event_type_id' => 'calendar',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar', 'weight' => 1]));
    $previousBooking = new PreviousSequenceBooking(
        sourceId: 'google_calendar',
        externalId: 'weekly-sync',
        customerId: 'customerX',
        budgetId: 7,
        isInternal: true,
        startedAt: Carbon::parse('2026-07-09 10:00'),
    );

    $activities = (new ActivityProjector)->project(collect([$meeting]), collect(), collect([$previousBooking]));

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->customer_id)->toBe('customerX')
        ->and($activities[0]->budget_id)->toBe(7)
        ->and($activities[0]->is_internal)->toBeTrue()
        ->and($meeting->customer_id)->toBeNull();
});

test('an event with a customer of its own ignores a previous sequence entry of another customer', function () {
    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'source_id' => 'google_calendar',
        'external_id' => 'weekly-sync',
        'event_type_id' => 'calendar',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar', 'weight' => 1]));
    $previousBooking = new PreviousSequenceBooking(
        sourceId: 'google_calendar',
        externalId: 'weekly-sync',
        customerId: 'customerX',
        budgetId: 7,
        isInternal: false,
        startedAt: Carbon::parse('2026-07-09 10:00'),
    );

    $activities = (new ActivityProjector)->project(collect([$meeting]), collect(), collect([$previousBooking]));

    expect($activities[0]->customer_id)->toBe('customerY')
        ->and($activities[0]->budget_id)->toBeNull();
});

test('a budgetless event takes the budget from the previous sequence entry of the same customer', function () {
    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'source_id' => 'google_calendar',
        'external_id' => 'weekly-sync',
        'event_type_id' => 'calendar',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar', 'weight' => 1]));
    $previousBooking = new PreviousSequenceBooking(
        sourceId: 'google_calendar',
        externalId: 'weekly-sync',
        customerId: 'customerX',
        budgetId: 7,
        isInternal: false,
        startedAt: Carbon::parse('2026-07-09 10:00'),
    );

    $activities = (new ActivityProjector)->project(collect([$meeting]), collect(), collect([$previousBooking]));

    expect($activities[0]->customer_id)->toBe('customerX')
        ->and($activities[0]->budget_id)->toBe(7);
});

test('a meeting booked like its previous sequence entry chains with adjacent work of that customer', function () {
    $meeting = new Event([
        'user_id' => 1,
        'source_id' => 'google_calendar',
        'external_id' => 'weekly-sync',
        'event_type_id' => 'calendar',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar', 'weight' => 1]));
    $work = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 10:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:50'),
    ]);
    $work->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 1]));
    $previousBooking = new PreviousSequenceBooking(
        sourceId: 'google_calendar',
        externalId: 'weekly-sync',
        customerId: 'customerX',
        budgetId: null,
        isInternal: false,
        startedAt: Carbon::parse('2026-07-09 10:00'),
    );

    $activities = (new ActivityProjector)->project(collect([$meeting, $work]), collect(), collect([$previousBooking]));

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->customer_id)->toBe('customerX')
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:50'))
        ->and($activities[0]->events->all())->toBe([$meeting, $work]);
});

test('a commit, a pull request comment and an approval on one ticket become one activity in any input order', function () {
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 09:15'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 70]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));

    $fromShuffled = (new ActivityProjector)->project(collect([$approved, $commit, $commented]), collect());
    $fromChronological = (new ActivityProjector)->project(collect([$commit, $commented, $approved]), collect());

    foreach ([$fromShuffled, $fromChronological] as $activities) {
        expect($activities)->toHaveCount(1)
            ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
            ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:45'))
            ->and($activities[0]->events->all())->toEqualCanonicalizing([$commit, $commented, $approved]);
    }
});

test('an activity widened up to an earlier projected activity of the same ticket merges with it', function () {
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 09:00'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));
    $changesRequested = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_changes_requested',
        'ended_at' => Carbon::parse('2026-07-16 09:20'),
    ]);
    $changesRequested->setRelation('eventType', new EventType(['id' => 'pr_changes_requested', 'weight' => 50]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:40'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));

    $activities = (new ActivityProjector)->project(collect([$approved, $changesRequested, $commented]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 08:45'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:40'))
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$commented, $approved, $changesRequested]);
});

test('an entry booked between two activities of the same ticket keeps them apart', function () {
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 09:00'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));
    $changesRequested = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_changes_requested',
        'ended_at' => Carbon::parse('2026-07-16 09:20'),
    ]);
    $changesRequested->setRelation('eventType', new EventType(['id' => 'pr_changes_requested', 'weight' => 50]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:40'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $entry = new Entry([
        'started_at' => Carbon::parse('2026-07-16 09:20'),
        'ended_at' => Carbon::parse('2026-07-16 09:25'),
    ]);

    $activities = (new ActivityProjector)->project(collect([$approved, $changesRequested, $commented]), collect([$entry]));

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:25'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:40'))
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$commented])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 08:45'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:20'))
        ->and($activities[1]->events->all())->toEqualCanonicalizing([$approved, $changesRequested]);
});

test('a merged pull request joins the review on its ticket', function () {
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 09:15'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));
    $merged = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_merged',
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $merged->setRelation('eventType', new EventType(['id' => 'pr_merged', 'weight' => 70]));

    $activities = (new ActivityProjector)->project(collect([$approved, $merged]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:30'))
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$approved, $merged]);
});

test('adjacent events on different tickets stay separate activities', function () {
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 09:15'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 70]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));

    $activities = (new ActivityProjector)->project(collect([$commit, $commented]), collect());

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$commented])
        ->and($activities[1]->events->all())->toEqualCanonicalizing([$commit]);
});

test('another ticket worked on in the gap keeps two activities of the same ticket apart', function () {
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 09:00'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));
    $changesRequested = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_changes_requested',
        'ended_at' => Carbon::parse('2026-07-16 09:20'),
    ]);
    $changesRequested->setRelation('eventType', new EventType(['id' => 'pr_changes_requested', 'weight' => 50]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:40'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $otherTicket = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'branch_created',
        'started_at' => Carbon::parse('2026-07-16 09:20'),
        'ended_at' => Carbon::parse('2026-07-16 09:25'),
    ]);
    $otherTicket->setRelation('eventType', new EventType(['id' => 'branch_created', 'weight' => 10]));

    $activities = (new ActivityProjector)->project(collect([$approved, $changesRequested, $commented, $otherTicket]), collect());

    expect($activities)->toHaveCount(3)
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$otherTicket])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 09:25'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:40'))
        ->and($activities[1]->events->all())->toEqualCanonicalizing([$commented])
        ->and($activities[2]->started_at)->toEqual(Carbon::parse('2026-07-16 08:45'))
        ->and($activities[2]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:20'))
        ->and($activities[2]->events->all())->toEqualCanonicalizing([$approved, $changesRequested]);
});

test('a new activity next to an activity of the same ticket merges with it', function () {
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'branch_created',
        'ended_at' => Carbon::parse('2026-07-16 09:10'),
    ]);
    $first->setRelation('eventType', new EventType(['id' => 'branch_created', 'weight' => 10]));
    $otherTicket = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'prompt_submitted',
        'ended_at' => Carbon::parse('2026-07-16 09:00'),
    ]);
    $otherTicket->setRelation('eventType', new EventType(['id' => 'prompt_submitted', 'weight' => 20]));
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'ended_at' => Carbon::parse('2026-07-16 09:20'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 30]));

    $activities = (new ActivityProjector)->project(collect([$first, $otherTicket, $commit]), collect());

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 08:55'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:20'))
        ->and($activities[0]->events->all())->toEqualCanonicalizing([$first, $commit])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 08:45'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 08:55'))
        ->and($activities[1]->events->all())->toEqualCanonicalizing([$otherTicket]);
});

test('merging the parts of a split event lists each event once', function () {
    $otherTicket = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'branch_created',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $otherTicket->setRelation('eventType', new EventType(['id' => 'branch_created', 'weight' => 10]));
    $work = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:55'),
        'ended_at' => Carbon::parse('2026-07-16 10:40'),
    ]);
    $work->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $long = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'browser_focus',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $long->setRelation('eventType', new EventType(['id' => 'browser_focus', 'weight' => 100]));

    $activities = (new ActivityProjector)->project(collect([$otherTicket, $work, $long]), collect());

    $periods = $activities
        ->map(fn ($activity) => $activity->ticket_number.' '.$activity->started_at->format('H:i').'-'.$activity->ended_at->format('H:i'))
        ->all();
    expect($periods)->toBe(['TIC-2 10:00-10:30', 'TIC-1 09:00-10:00', 'TIC-1 10:30-12:00'])
        ->and($activities[1]->events->all())->toBe([$work, $long])
        ->and($activities[2]->events->all())->toBe([$work, $long]);
});

test('a fill event widening an activity across a booked entry splits it around the entry', function () {
    $work = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:55'),
        'ended_at' => Carbon::parse('2026-07-16 10:40'),
    ]);
    $work->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $long = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'browser_focus',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $long->setRelation('eventType', new EventType(['id' => 'browser_focus', 'weight' => 100]));
    $entry = new Entry([
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);

    $activities = (new ActivityProjector)->project(collect([$work, $long]), collect([$entry]));

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->events->all())->toBe([$work, $long])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 10:30'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 12:00'))
        ->and($activities[1]->events->all())->toBe([$work, $long]);
});

test('a Claude Code session for one customer and a review for another customer both keep their full time', function () {
    $session = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'prompt_submitted',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $session->setRelation('eventType', new EventType(['id' => 'prompt_submitted', 'weight' => 20]));
    $commented = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'pr_commented',
        'ended_at' => Carbon::parse('2026-07-16 09:40'),
    ]);
    $commented->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $approved = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'pr_approved',
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $approved->setRelation('eventType', new EventType(['id' => 'pr_approved', 'weight' => 50]));

    $activities = (new ActivityProjector)->project(collect([$session, $commented, $approved]), collect());

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($activities[0]->events->all())->toBe([$session])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 09:25'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[1]->events->all())->toBe([$commented, $approved]);
});

test('overlapping work on the same ticket becomes one activity', function () {
    $session = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'prompt_submitted',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $session->setRelation('eventType', new EventType(['id' => 'prompt_submitted', 'weight' => 20]));
    $review = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $review->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));

    $activities = (new ActivityProjector)->project(collect([$review, $session]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:30'))
        ->and($activities[0]->events->all())->toBe([$session, $review]);
});

test('a booked entry only claims time from activities of its own customer', function () {
    $session = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'prompt_submitted',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $session->setRelation('eventType', new EventType(['id' => 'prompt_submitted', 'weight' => 20]));
    $review = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $review->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $otherTicket = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-3',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $otherTicket->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 10]));
    $entry = new Entry([
        'customer_id' => 'customerX',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:15'),
    ]);

    $activities = (new ActivityProjector)->project(collect([$session, $review, $otherTicket]), collect([$entry]));

    $periods = $activities
        ->map(fn ($activity) => $activity->ticket_number.' '.$activity->started_at->format('H:i').'-'.$activity->ended_at->format('H:i'))
        ->all();
    expect($periods)->toBe(['TIC-3 09:30-10:00', 'TIC-3 10:15-10:30', 'TIC-1 09:00-09:30', 'TIC-1 10:30-11:00', 'TIC-2 09:30-10:30']);
});

test('a ticketless meeting held back for a neighbour still claims its time before a browser session', function () {
    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 75]));
    $browsing = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'browser_focus',
        'started_at' => Carbon::parse('2026-07-16 10:30'),
        'ended_at' => Carbon::parse('2026-07-16 11:30'),
    ]);
    $browsing->setRelation('eventType', new EventType(['id' => 'browser_focus', 'weight' => 100]));

    $activities = (new ActivityProjector)->project(collect([$meeting, $browsing]), collect());

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($activities[0]->events->all())->toBe([$meeting])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 11:30'))
        ->and($activities[1]->events->all())->toBe([$browsing]);
});

test('an event chained across a booked entry splits the activity around the entry', function () {
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 10]));
    $review = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:38'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $review->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $entry = new Entry([
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:40'),
    ]);

    $activities = (new ActivityProjector)->project(collect([$commit, $review]), collect([$entry]));

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 09:35'))
        ->and($activities[0]->events->all())->toBe([$commit])
        ->and($activities[1]->started_at)->toEqual(Carbon::parse('2026-07-16 09:40'))
        ->and($activities[1]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($activities[1]->events->all())->toBe([$review]);
});

test('shuffled overlapping work produces the same activities as chronological input', function () {
    $makeEvents = function () {
        $events = [];
        foreach ([
            ['09:00', '11:00', 'customerX', 'TIC-1', 20],
            [null, '09:40', 'customerY', 'TIC-2', 40],
            [null, '10:00', 'customerY', 'TIC-2', 50],
            ['09:50', '10:20', 'customerX', 'TIC-1', 40],
            ['08:30', '12:00', 'customerY', null, 100],
        ] as [$start, $end, $customer, $ticket, $weight]) {
            $event = new Event([
                'user_id' => 1,
                'customer_id' => $customer,
                'ticket_number' => $ticket,
                'started_at' => $start === null ? null : Carbon::parse("2026-07-16 $start"),
                'ended_at' => Carbon::parse("2026-07-16 $end"),
            ]);
            $event->setRelation('eventType', new EventType(['weight' => $weight]));
            $events[] = $event;
        }

        return $events;
    };
    $entries = collect([new Entry(['started_at' => Carbon::parse('2026-07-16 10:30'), 'ended_at' => Carbon::parse('2026-07-16 10:45')])]);

    $chronological = (new ActivityProjector)->project(collect($makeEvents()), $entries);
    $shuffled = (new ActivityProjector)->project(collect(array_reverse($makeEvents())), $entries);

    $signature = fn ($activities) => $activities
        ->map(fn ($activity) => $activity->customer_id.'|'.$activity->ticket_number.'|'.$activity->started_at->format('H:i').'|'.$activity->ended_at->format('H:i').'|'.$activity->events->count())
        ->sort()->values()->all();
    expect($signature($shuffled))->toBe($signature($chronological))
        ->and($signature($chronological))->toBe([
            'customerX|TIC-1|09:00|10:30|2',
            'customerX|TIC-1|10:45|11:00|1',
            'customerY|TIC-2|08:30|09:00|1',
            'customerY|TIC-2|09:25|10:00|3',
            'customerY|TIC-2|11:00|12:00|1',
        ]);
});

test('a browser event chained onto an activity leaves its overlap with other work intact', function () {
    $session = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'prompt_submitted',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $session->setRelation('eventType', new EventType(['id' => 'prompt_submitted', 'weight' => 20]));
    $review = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'pr_commented',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:45'),
    ]);
    $review->setRelation('eventType', new EventType(['id' => 'pr_commented', 'weight' => 40]));
    $browsing = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'browser_focus',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $browsing->setRelation('eventType', new EventType(['id' => 'browser_focus', 'weight' => 100]));

    $activities = (new ActivityProjector)->project(collect([$session, $review, $browsing]), collect());

    $periods = $activities
        ->map(fn ($activity) => $activity->ticket_number.' '.$activity->started_at->format('H:i').'-'.$activity->ended_at->format('H:i'))
        ->all();
    expect($periods)->toBe(['TIC-1 09:00-10:00', 'TIC-2 09:30-10:45'])
        ->and($activities[0]->events->all())->toBe([$session, $browsing]);
});

test('a ticketless meeting chained onto one ticket does not double count another ticket of its customer', function () {
    $otherTicket = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'branch_created',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $otherTicket->setRelation('eventType', new EventType(['id' => 'branch_created', 'weight' => 10]));
    $commit = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_created',
        'started_at' => Carbon::parse('2026-07-16 08:50'),
        'ended_at' => Carbon::parse('2026-07-16 09:00'),
    ]);
    $commit->setRelation('eventType', new EventType(['id' => 'commit_created', 'weight' => 20]));
    $meeting = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 12:00'),
    ]);
    $meeting->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 75]));

    $activities = (new ActivityProjector)->project(collect([$otherTicket, $commit, $meeting]), collect());

    $periods = $activities
        ->map(fn ($activity) => $activity->ticket_number.' '.$activity->started_at->format('H:i').'-'.$activity->ended_at->format('H:i'))
        ->all();
    expect($periods)->toBe(['TIC-2 09:30-11:00', 'TIC-1 08:50-09:30', 'TIC-1 11:00-12:00']);
});
