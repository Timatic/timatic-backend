<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Entry;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class ActivityProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    /** @var Collection<int, Activity> */
    private Collection $activities;

    /** @var Collection<int, Entry> */
    private Collection $entries;

    public function __construct()
    {
        $this->activities = collect();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Entry>  $entries
     * @return Collection<int, Activity>
     */
    public function project(Collection $events, Collection $entries): Collection
    {
        $this->entries = $entries;

        $sorted = $events->sort(function (Event $a, Event $b) {
            return ((int) $a->eventType?->weight <=> (int) $b->eventType?->weight)
                ?: $a->effectiveStart()->getTimestamp() <=> $b->effectiveStart()->getTimestamp();
        })->values();

        /** @var Collection<int, Event> $unclaimed */
        $unclaimed = collect();

        foreach ($sorted as $event) {
            if ($event->customer_id === null) {
                $this->createActivity($event);

                continue;
            }

            if ($event->ticket_number === null) {
                $match = $this->findChainableActivity($event,
                    fn (Activity $a) => $a->customer_id === $event->customer_id
                );

                if ($match) {
                    $this->appendEventToActivity($match, $event);
                } else {
                    $unclaimed->push($event);
                }

                continue;
            }

            $match = $this->findChainableActivity($event,
                fn (Activity $a) => $a->customer_id === $event->customer_id
                    && $a->ticket_number === $event->ticket_number
                    && $a->event_type_id === $event->event_type_id
            );

            if ($match) {
                $this->appendEventToActivity($match, $event);
            } else {
                $this->createActivity($event);
            }
        }

        foreach ($unclaimed as $event) {
            $match = $this->findChainableActivity($event,
                fn (Activity $a) => $a->customer_id === $event->customer_id
            );

            if ($match) {
                $this->appendEventToActivity($match, $event);
            } else {
                $this->createActivity($event);
            }
        }

        return $this->activities;
    }

    private function createActivity(Event $event): void
    {
        $newActivity = $this->activityFromEvent($event);
        $newActivityPartsWithoutOverlap = $this->reduceOverlap($newActivity);
        $this->activities->push(...$newActivityPartsWithoutOverlap);
    }

    /**
     * @return array<int, Activity>
     */
    private function reduceOverlap(Activity $activity): array
    {
        $claimed = new PeriodCollection(
            ...$this->entries->map(fn (Entry $entry) => $this->period($entry->started_at, $entry->ended_at))->all(),
            ...$this->activities->map(fn (Activity $activity) => $this->period($activity->started_at, $activity->ended_at))->all(),
        );

        $activityPeriod = $this->period($activity->started_at, $activity->ended_at);
        $segments = PeriodCollection::make($activityPeriod)->subtract($claimed);

        if ($segments->isEmpty()) {
            return [];
        }

        if ($segments->count() === 1) {
            $activity->started_at = Carbon::instance($segments[0]->start());
            $activity->ended_at = Carbon::instance($segments[0]->end());

            return [$activity];
        }

        $parts = [];
        foreach ($segments as $segment) {
            $partialActivity = $activity->replicate(except: ['started_at', 'ended_at']);
            $partialActivity->started_at = Carbon::instance($segment->start());
            $partialActivity->ended_at = Carbon::instance($segment->end());

            $events = $activity->events->whereBetween('ended_at', [$segment->start(), $segment->end()]);
            $partialActivity->setRelation('events', $events);
            $parts[] = $partialActivity;
        }

        return $parts;
    }

    /**
     * @param  Closure(Activity): bool  $matches
     */
    private function findChainableActivity(Event $event, Closure $matches): ?Activity
    {
        $eventStart = $event->effectiveStart();
        $eventEnd = $event->ended_at->copy();

        $precedingActivity = $this->activities
            ->filter(fn (Activity $a) => $a->ended_at->isBefore($eventEnd)
                && $a->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES)->isAfter($eventStart)
            )
            ->last();

        if ($precedingActivity && $matches($precedingActivity)) {
            return $precedingActivity;
        }

        $followingActivity = $this->activities
            ->filter(fn (Activity $a) => $a->started_at->isAfter($eventStart)
                && $a->started_at->copy()->subMinutes(self::CHAIN_GAP_MINUTES)->isBefore($eventEnd)
            )
            ->first();

        if ($followingActivity && $matches($followingActivity)) {
            return $followingActivity;
        }

        return null;
    }

    private function activityFromEvent(Event $event): Activity
    {
        $activity = new Activity;
        $activity->source_id = $event->source_id;
        $activity->user_id = $event->user_id;
        $activity->budget_id = $event->budget_id;
        $activity->ticket_id = $event->ticket_id;
        $activity->ticket_number = $event->ticket_number;
        $activity->ticket_type = $event->ticket_type;
        $activity->title = $event->title;
        $activity->description = $event->description;
        $activity->customer_id = $event->customer_id;
        $activity->is_internal = $event->is_internal;
        $activity->event_type_id = $event->event_type_id;
        $activity->started_at = Carbon::instance($event->effectiveStart());
        $activity->ended_at = $event->ended_at;
        $activity->setRelation('events', collect([$event]));
        $activity->setRelation('eventType', $event->eventType);

        return $activity;
    }

    private function appendEventToActivity(Activity $activity, Event $event): void
    {
        $activity->events->push($event);
        $effectiveStart = $event->effectiveStart();

        if ($effectiveStart->isBefore($activity->started_at)) {
            $activity->started_at = Carbon::instance($effectiveStart);
        }

        if ($event->ended_at->isAfter($activity->ended_at)) {
            $activity->ended_at = $event->ended_at;
        }
    }

    private function period(CarbonInterface $start, CarbonInterface $end): Period
    {
        return new Period(
            DateTimeImmutable::createFromInterface($start),
            DateTimeImmutable::createFromInterface($end),
            Precision::SECOND(),
            Boundaries::EXCLUDE_END(),
        );
    }
}
