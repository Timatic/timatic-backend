<?php

namespace App\Services;

use App\DataTransferObjects\Period;
use App\Models\Activity;
use App\Models\Event;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

class ActivityProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    private const ESTIMATED_DURATION_MINUTES = 15;

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Period>  $entryPeriods
     * @return Collection<int, Activity>
     */
    public function project(Collection $events, Collection $entryPeriods): Collection
    {
        $groups = $this->chainEventsIntoGroups($events);
        $activities = $this->resolveWeightDominance($groups);

        return $this->trimAroundEntryPeriods($activities, $entryPeriods)->values();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, EventGroup>
     */
    private function chainEventsIntoGroups(Collection $events): Collection
    {
        $sorted = $events->sortBy(fn (Event $event) => $this->effectiveStart($event))->values();

        /** @var Collection<int, EventGroup> $groups */
        $groups = collect();
        /** @var Collection<int, Event> $unclaimed */
        $unclaimed = collect();

        foreach ($sorted as $event) {
            if ($event->customer_id === null) {
                $groups->push($this->newGroup($event));

                continue;
            }

            if ($event->ticket_number === null) {
                $preceding = $this->lastChainableGroup($groups, $event,
                    fn (EventGroup $group) => $group->customerId === $event->customer_id);
                $preceding ? $preceding->add($event, $this->effectiveStart($event)) : $unclaimed->push($event);

                continue;
            }

            $matching = $this->lastChainableGroup($groups, $event,
                fn (EventGroup $group) => $group->customerId === $event->customer_id
                    && $group->ticketNumber === $event->ticket_number
                    && $group->eventTypeId === $event->event_type_id);
            $matching ? $matching->add($event, $this->effectiveStart($event)) : $groups->push($this->newGroup($event));
        }

        foreach ($unclaimed as $event) {
            $following = $groups->first(fn (EventGroup $group) => $group->customerId === $event->customer_id
                && $group->startedAt->lessThanOrEqualTo($event->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES))
                && $group->endedAt->greaterThanOrEqualTo($this->effectiveStart($event)));
            $following ? $following->add($event, $this->effectiveStart($event)) : $groups->push($this->newGroup($event));
        }

        return $groups;
    }

    /**
     * @param  Collection<int, EventGroup>  $groups
     * @param  Closure(EventGroup): bool  $matches
     */
    private function lastChainableGroup(Collection $groups, Event $event, Closure $matches): ?EventGroup
    {
        $effectiveStart = $this->effectiveStart($event);

        return $groups->last(fn (EventGroup $group) => $matches($group)
            && $effectiveStart->lessThanOrEqualTo($group->endedAt->copy()->addMinutes(self::CHAIN_GAP_MINUTES)));
    }

    private function newGroup(Event $event): EventGroup
    {
        return new EventGroup(
            customerId: $event->customer_id,
            ticketNumber: $event->ticket_number,
            eventTypeId: $event->event_type_id,
            event: $event,
            effectiveStart: $this->effectiveStart($event),
        );
    }

    private function effectiveStart(Event $event): CarbonInterface
    {
        return $event->started_at ?: $event->ended_at->copy()->subMinutes(self::ESTIMATED_DURATION_MINUTES);
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function activityFromGroup(EventGroup $group, Period $period, Collection $events): Activity
    {
        /** @var Event $template */
        $template = $events->sortBy(fn (Event $event) => $this->effectiveStart($event))->first();

        $activity = new Activity;
        $activity->source_id = $template->source_id;
        $activity->user_id = $template->user_id;
        $activity->budget_id = $template->budget_id;
        $activity->ticket_id = $template->ticket_id;
        $activity->ticket_number = $group->ticketNumber;
        $activity->ticket_type = $template->ticket_type;
        $activity->title = $template->title;
        $activity->description = $template->description;
        $activity->customer_id = $group->customerId;
        $activity->is_internal = $template->is_internal;
        $activity->event_type_id = $group->eventTypeId;
        $activity->started_at = $period->startedAt;
        $activity->ended_at = $period->endedAt;
        $activity->setRelation('events', $events->values());
        $activity->setRelation('eventType', $template->eventType);

        return $activity;
    }

    /**
     * @param  Collection<int, EventGroup>  $groups
     * @return Collection<int, Activity>
     */
    private function resolveWeightDominance(Collection $groups): Collection
    {
        $ranked = $groups->sortBy([
            fn (EventGroup $a, EventGroup $b) => $this->weight($b) <=> $this->weight($a),
            fn (EventGroup $a, EventGroup $b) => $a->startedAt <=> $b->startedAt,
        ])->values();

        /** @var Collection<int, Activity> $accepted */
        $accepted = collect();

        foreach ($ranked as $group) {
            $blockers = $accepted->map(fn (Activity $activity) => new Period($activity->started_at, $activity->ended_at));
            $segments = $group->period()->subtract($blockers);

            $accepted = $accepted->concat($this->activitiesFromSegments($group, $segments, $accepted));
        }

        return $accepted;
    }

    /**
     * @param  Collection<int, Period>  $segments
     * @param  Collection<int, Activity>  $coveringCandidates
     * @return Collection<int, Activity>
     */
    private function activitiesFromSegments(EventGroup $group, Collection $segments, Collection $coveringCandidates): Collection
    {
        /** @var Collection<int, Activity> $activities */
        $activities = collect();
        $remaining = $group->events;

        foreach ($segments as $segment) {
            [$segmentEvents, $remaining] = $remaining->partition(fn (Event $event) => $this->eventPeriod($event)->overlaps($segment));

            if ($segmentEvents->isEmpty()) {
                continue;
            }

            $activities->push($this->activityFromGroup($group, $segment, $segmentEvents->values()));
        }

        $remaining->each(fn (Event $event) => $this->attachToCoveringActivity($event, $group, $coveringCandidates));

        return $activities;
    }

    /**
     * @param  Collection<int, Activity>  $candidates
     */
    private function attachToCoveringActivity(Event $event, EventGroup $group, Collection $candidates): void
    {
        $covering = $candidates->first(fn (Activity $activity) => $activity->customer_id === $group->customerId
            && $activity->ticket_number === $group->ticketNumber
            && (new Period($activity->started_at, $activity->ended_at))->covers($this->eventPeriod($event)));

        $covering?->events->push($event);
    }

    private function eventPeriod(Event $event): Period
    {
        return new Period($this->effectiveStart($event), $event->ended_at);
    }

    private function weight(EventGroup $group): int
    {
        return (int) $group->events->first()?->eventType?->weight;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Period>  $entryPeriods
     * @return Collection<int, Activity>
     */
    private function trimAroundEntryPeriods(Collection $activities, Collection $entryPeriods): Collection
    {
        if ($entryPeriods->isEmpty()) {
            return $activities;
        }

        return $activities->flatMap(function (Activity $activity) use ($entryPeriods) {
            $segments = (new Period($activity->started_at, $activity->ended_at))->subtract($entryPeriods);

            if ($segments->count() === 1 && $segments[0]->startedAt->equalTo($activity->started_at) && $segments[0]->endedAt->equalTo($activity->ended_at)) {
                return [$activity];
            }

            $splits = [];
            $remaining = $activity->events;
            foreach ($segments as $segment) {
                [$segmentEvents, $remaining] = $remaining->partition(fn (Event $event) => $this->eventPeriod($event)->overlaps($segment));

                if ($segmentEvents->isEmpty()) {
                    continue;
                }

                $splits[] = $this->cloneActivityForSegment($activity, $segment, $segmentEvents->values());
            }

            return $splits;
        });
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function cloneActivityForSegment(Activity $activity, Period $segment, Collection $events): Activity
    {
        $split = $activity->replicate(['started_at', 'ended_at']);
        $split->started_at = $segment->startedAt;
        $split->ended_at = $segment->endedAt;
        $split->setRelation('events', $events);
        $split->setRelation('eventType', $activity->eventType);

        return $split;
    }
}
