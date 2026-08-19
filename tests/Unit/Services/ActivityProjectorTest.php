<?php

use App\Models\Event;
use App\Models\EventType;
use App\Services\ActivityProjector;
use Carbon\Carbon;

test('an event without start becomes an activity starting 15 minutes before its end', function () {
    $event = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));

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
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $event->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$event]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->started_at)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($activities[0]->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'));
});

test('same-ticket events within the chain gap merge into one activity across sources', function () {
    $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'source_id' => 'bitbucket',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
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
    $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:46'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $second->setRelation('eventType', $eventType);

    $activities = (new ActivityProjector)->project(collect([$first, $second]), collect());

    expect($activities)->toHaveCount(2);
});

test('events of different customers never merge', function () {
    $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
    $first = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $first->setRelation('eventType', $eventType);
    $second = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
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
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $ticketed->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));
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
    $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
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
        'event_type_id' => 'commit_pushed',
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
    $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
    $preceding = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $preceding->setRelation('eventType', $eventType);
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => null,
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $ticketless->setRelation('eventType', $eventType);
    $following = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_pushed',
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
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 09:30'),
    ]);
    $ticketed->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));
    $ticketless = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => null,
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:35'),
        'ended_at' => Carbon::parse('2026-07-16 09:45'),
    ]);
    $ticketless->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$ticketed, $ticketless]), collect());

    expect($activities)->toHaveCount(2);
});

test('the higher-weight group keeps its period and the lower one is trimmed', function () {
    $light = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 00:10'),
        'ended_at' => Carbon::parse('2026-07-16 00:20'),
    ]);
    $light->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));
    $heavy = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 00:05'),
        'ended_at' => Carbon::parse('2026-07-16 00:15'),
    ]);
    $heavy->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 999]));

    $activities = (new ActivityProjector)->project(collect([$light, $heavy]), collect());

    $heavyActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-2');
    $lightActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-1');
    expect($activities)->toHaveCount(2)
        ->and($heavyActivity->started_at)->toEqual(Carbon::parse('2026-07-16 00:05'))
        ->and($heavyActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 00:15'))
        ->and($lightActivity->started_at)->toEqual(Carbon::parse('2026-07-16 00:15'))
        ->and($lightActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 00:20'));
});

test('a group fully covered by a matching dominant group attaches its events to the covering activity', function () {
    $covering = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $covering->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 999]));
    $covered = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 10:05'),
        'ended_at' => Carbon::parse('2026-07-16 10:10'),
    ]);
    $covered->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$covering, $covered]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->events)->toHaveCount(2);
});

test('a covered group of another customer stays unattached instead of mixing customers', function () {
    $covering = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $covering->setRelation('eventType', new EventType(['id' => 'calendar_event_started', 'weight' => 999]));
    $covered = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 10:05'),
        'ended_at' => Carbon::parse('2026-07-16 10:10'),
    ]);
    $covered->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 1]));

    $activities = (new ActivityProjector)->project(collect([$covering, $covered]), collect());

    expect($activities)->toHaveCount(1)
        ->and($activities[0]->events)->toHaveCount(1)
        ->and($activities[0]->events->first())->toBe($covering);
});

test('on equal weight the earlier-starting group is dominant', function () {
    $earlier = new Event([
        'user_id' => 1,
        'customer_id' => 'customerX',
        'ticket_number' => 'TIC-1',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:00'),
    ]);
    $earlier->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 5]));
    $later = new Event([
        'user_id' => 1,
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 09:30'),
        'ended_at' => Carbon::parse('2026-07-16 10:30'),
    ]);
    $later->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 5]));

    $activities = (new ActivityProjector)->project(collect([$earlier, $later]), collect());

    $earlierActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-1');
    $laterActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-2');
    expect($earlierActivity->ended_at)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($laterActivity->started_at)->toEqual(Carbon::parse('2026-07-16 10:00'));
});

test('a subordinate group starts after the latest dominant overlapping group', function () {
    $heavyType = new EventType(['id' => 'calendar_event_started', 'weight' => 999]);
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
        'customer_id' => 'customerY',
        'ticket_number' => 'TIC-2',
        'event_type_id' => 'calendar_event_started',
        'started_at' => Carbon::parse('2026-07-16 10:00'),
        'ended_at' => Carbon::parse('2026-07-16 10:20'),
    ]);
    $secondDominant->setRelation('eventType', $heavyType);
    $subordinate = new Event([
        'user_id' => 1,
        'customer_id' => 'customerZ',
        'ticket_number' => 'TIC-3',
        'event_type_id' => 'commit_pushed',
        'started_at' => Carbon::parse('2026-07-16 10:15'),
        'ended_at' => Carbon::parse('2026-07-16 11:00'),
    ]);
    $subordinate->setRelation('eventType', new EventType(['id' => 'commit_pushed', 'weight' => 5]));

    $activities = (new ActivityProjector)->project(collect([$firstDominant, $secondDominant, $subordinate]), collect());

    $subordinateActivity = $activities->first(fn ($activity) => $activity->ticket_number === 'TIC-3');
    expect($subordinateActivity->started_at)->toEqual(Carbon::parse('2026-07-16 10:30'));
});

test('shuffled input produces the same activities as chronological input', function () {
    $makeEvents = function () {
        $eventType = new EventType(['id' => 'commit_pushed', 'weight' => 1]);
        $events = [];
        foreach ([['09:00', '09:20', 'TIC-1'], ['09:25', '09:40', 'TIC-1'], ['10:30', '11:00', 'TIC-2']] as [$start, $end, $ticket]) {
            $event = new Event([
                'user_id' => 1,
                'customer_id' => 'customerX',
                'ticket_number' => $ticket,
                'event_type_id' => 'commit_pushed',
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
