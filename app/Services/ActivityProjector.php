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

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Entry>  $entries
     * @return Collection<int, Activity>
     */
    public function project(Collection $events, Collection $entries): Collection
    {
        $activities = $this->chainEvents($events);

        return $this->reduceOverlap($activities, $entries);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Activity>
     */
    private function chainEvents(Collection $events): Collection
    {
        $sorted = $events->sort(function (Event $a, Event $b) {
            return ((int) $b->eventType?->weight <=> (int) $a->eventType?->weight)
                ?: $a->effectiveStart()->getTimestamp() <=> $b->effectiveStart()->getTimestamp();
        })->values();

        /** @var Collection<int, Activity> $activities */
        $activities = collect();
        /** @var Collection<int, Event> $unclaimed */
        $unclaimed = collect();

        foreach ($sorted as $event) {
            if ($event->customer_id === null) {
                $activities->push($this->activityFromEvent($event));

                continue;
            }

            if ($event->ticket_number === null) {
                $match = $this->findChainableActivity($activities, $event,
                    fn (Activity $a) => $a->customer_id === $event->customer_id
                );

                if ($match) {
                    $this->appendEvent($match, $event);
                } else {
                    $unclaimed->push($event);
                }

                continue;
            }

            $match = $this->findChainableActivity($activities, $event,
                fn (Activity $a) => $a->customer_id === $event->customer_id
                    && $a->ticket_number === $event->ticket_number
                    && $a->event_type_id === $event->event_type_id
            );

            if ($match) {
                $this->appendEvent($match, $event);
            } else {
                $activities->push($this->activityFromEvent($event));
            }
        }

        foreach ($unclaimed as $event) {
            $match = $this->findChainableActivity($activities, $event,
                fn (Activity $a) => $a->customer_id === $event->customer_id
            );

            if ($match) {
                $this->appendEvent($match, $event);
            } else {
                $activities->push($this->activityFromEvent($event));
            }
        }

        return $activities;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Entry>  $entries
     * @return Collection<int, Activity>
     */
    private function reduceOverlap(Collection $activities, Collection $entries): Collection
    {
        $sorted = $activities->sort(function (Activity $a, Activity $b) {
            return ((int) $b->eventType?->weight <=> (int) $a->eventType?->weight)
                ?: $a->started_at->getTimestamp() <=> $b->started_at->getTimestamp();
        })->values();

        $claimed = new PeriodCollection(
            ...$entries->map(fn (Entry $entry) => $this->period($entry->started_at, $entry->ended_at))->all()
        );

        /** @var Collection<int, Activity> $result */
        $result = collect();

        foreach ($sorted as $activity) {
            $activityPeriod = $this->period($activity->started_at, $activity->ended_at);
            $segments = PeriodCollection::make($activityPeriod)->subtract($claimed);

            if ($segments->isEmpty()) {
                $this->attachEventsToCovers($activity, $result);

                continue;
            }

            foreach ($segments as $segment) {
                $segmentEvents = $activity->events->filter(
                    fn (Event $event) => $this->period($event->effectiveStart(), $event->ended_at)
                        ->overlapsWith($segment)
                );

                if ($segmentEvents->isEmpty()) {
                    continue;
                }

                $result->push($this->splitActivity($activity, $segment, $segmentEvents->values()));
            }

            $claimed = $claimed->add($activityPeriod);
        }

        return $result->values();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Closure(Activity): bool  $matches
     */
    private function findChainableActivity(Collection $activities, Event $event, Closure $matches): ?Activity
    {
        return $this->precedingChainableActivity($activities, $event, $matches)
            ?? $this->followingChainableActivity($activities, $event, $matches);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Closure(Activity): bool  $matches
     */
    private function precedingChainableActivity(Collection $activities, Event $event, Closure $matches): ?Activity
    {
        $eventStart = $event->effectiveStart();

        return $activities
            ->filter(fn (Activity $a) => $eventStart->isBefore($a->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES)))
            ->last(fn (Activity $a) => $matches($a));
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Closure(Activity): bool  $matches
     */
    private function followingChainableActivity(Collection $activities, Event $event, Closure $matches): ?Activity
    {
        $eventStart = $event->effectiveStart();
        $eventEnd = $event->ended_at->copy();

        return $activities
            ->filter(fn (Activity $a) => $a->ended_at->isAfter($eventStart)
                && $a->started_at->isBefore($eventEnd->addMinutes(self::CHAIN_GAP_MINUTES))
            )
            ->first(fn (Activity $a) => $matches($a));
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

    private function appendEvent(Activity $activity, Event $event): void
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

    /**
     * @param  Collection<int, Activity>  $candidates
     */
    private function attachEventsToCovers(Activity $covered, Collection $candidates): void
    {
        foreach ($covered->events as $event) {
            $eventPeriod = $this->period($event->effectiveStart(), $event->ended_at);

            $covering = $candidates->first(fn (Activity $a) => $a->customer_id === $covered->customer_id
                && $a->ticket_number === $covered->ticket_number
                && $this->period($a->started_at, $a->ended_at)->contains($eventPeriod));

            $covering?->events->push($event);
        }
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function splitActivity(Activity $activity, Period $segment, Collection $events): Activity
    {
        $split = $activity->replicate(['started_at', 'ended_at']);
        $split->started_at = Carbon::instance($segment->start());
        $split->ended_at = Carbon::instance($segment->end());
        $split->setRelation('events', $events);
        $split->setRelation('eventType', $activity->eventType);

        return $split;
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
