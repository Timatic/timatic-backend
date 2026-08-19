<?php

namespace App\Services;

use App\DataTransferObjects\EventGroup;
use App\DataTransferObjects\TimeSlot;
use App\Models\Activity;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;

class ActivityProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    private const ESTIMATED_DURATION_MINUTES = 15;

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, TimeSlot>  $entryTimeSlots
     * @return Collection<int, Activity>
     */
    public function project(Collection $events, Collection $entryTimeSlots): Collection
    {
        $activities = $this->buildActivities($events);

        return $this->trimAroundEntryPeriods($activities, $entryTimeSlots)->values();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Activity>
     */
    private function buildActivities(Collection $events): Collection
    {
        $tiers = $events->groupBy(fn (Event $event) => (int) $event->eventType?->weight)
            ->sortKeysDesc();

        /** @var Collection<int, Activity> $activities */
        $activities = collect();
        $claimed = new PeriodCollection;

        foreach ($tiers as $tierEvents) {
            $groups = $this->chainTier($tierEvents);
            $tierClaimed = new PeriodCollection;

            foreach ($groups->sortBy('startedAt')->values() as $group) {
                $blockers = new PeriodCollection(...[...$claimed, ...$tierClaimed]);
                $groupPeriod = $group->period();
                $segments = PeriodCollection::make($groupPeriod)->subtract($blockers);

                if ($segments->isEmpty()) {
                    $group->events->each(fn (Event $event) => $this->attachToCoveringActivity($event, $group, $activities));

                    continue;
                }

                foreach ($segments as $segment) {
                    $segmentEvents = $group->events->filter(fn (Event $event) => $this->eventPeriod($event)->overlapsWith($segment));

                    if ($segmentEvents->isEmpty()) {
                        continue;
                    }

                    $activities->push($this->activityFromGroup($group, $segment, $segmentEvents));
                }

                $tierClaimed = $tierClaimed->add($groupPeriod);
            }

            $claimed = new PeriodCollection(...[...$claimed, ...$tierClaimed]);
        }

        return $activities;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, EventGroup>
     */
    private function chainTier(Collection $events): Collection
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

            $eventStart = $this->effectiveStart($event);

            if ($event->ticket_number === null) {
                $recentGroup = $this->recentChainableGroup($groups, $event,
                    fn (EventGroup $group) => $group->customerId === $event->customer_id
                );

                if ($recentGroup) {
                    $recentGroup->add($event, $eventStart);
                } else {
                    $unclaimed->push($event);
                }

                continue;
            }

            $matchingGroup = $this->recentChainableGroup($groups, $event,
                fn (EventGroup $group) => $group->customerId === $event->customer_id
                    && $group->ticketNumber === $event->ticket_number
                    && $group->eventTypeId === $event->event_type_id
            );

            if ($matchingGroup) {
                $matchingGroup->add($event, $eventStart);
            } else {
                $groups->push($this->newGroup($event));
            }
        }

        foreach ($unclaimed as $event) {
            $eventStart = $this->effectiveStart($event);

            $following = $this->recentChainableGroup($groups, $event,
                fn (EventGroup $group) => $group->customerId === $event->customer_id
            );

            $following ? $following->add($event, $eventStart) : $groups->push($this->newGroup($event));
        }

        return $groups;
    }

    /**
     * @param  Collection<int, EventGroup>  $groups
     * @param  Closure(EventGroup): bool  $matches
     */
    private function recentChainableGroup(Collection $groups, Event $event, Closure $matches): ?EventGroup
    {
        return $this->lastRecentChainableGroup($groups, $event, $matches)
            ?? $this->nextRecentChainableGroup($groups, $event, $matches);
    }

    /**
     * @param  Collection<int, EventGroup>  $groups
     * @param  Closure(EventGroup): bool  $matches
     */
    private function lastRecentChainableGroup(Collection $groups, Event $event, Closure $matches): ?EventGroup
    {
        $eventStart = $this->effectiveStart($event);

        return $groups
            ->filter(fn (EventGroup $group) => $eventStart->isBefore($group->endedAt->copy()->addMinutes(self::CHAIN_GAP_MINUTES)))
            ->last(fn (EventGroup $group) => $matches($group));
    }

    /**
     * @param  Collection<int, EventGroup>  $groups
     * @param  Closure(EventGroup): bool  $matches
     */
    private function nextRecentChainableGroup(Collection $groups, Event $event, Closure $matches): ?EventGroup
    {
        $eventStart = $this->effectiveStart($event);
        $eventEnd = $event->ended_at->copy();

        return $groups
            ->filter(fn (EventGroup $group) => $group->endedAt->isAfter($eventStart)
                && $group->startedAt->isBefore($eventEnd->addMinutes(self::CHAIN_GAP_MINUTES))
            )
            ->first(fn (EventGroup $group) => $matches($group));
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
        $activity->started_at = Carbon::instance($period->start());
        $activity->ended_at = Carbon::instance($period->end());
        $activity->setRelation('events', $events->values());
        $activity->setRelation('eventType', $template->eventType);

        return $activity;
    }

    /**
     * @param  Collection<int, Activity>  $candidates
     */
    private function attachToCoveringActivity(Event $event, EventGroup $group, Collection $candidates): void
    {
        $covering = $candidates->first(fn (Activity $activity) => $activity->customer_id === $group->customerId
            && $activity->ticket_number === $group->ticketNumber
            && (new TimeSlot($activity->started_at, $activity->ended_at))->contains($this->eventPeriod($event)));

        $covering?->events->push($event);
    }

    private function eventPeriod(Event $event): TimeSlot
    {
        return new TimeSlot($this->effectiveStart($event), $event->ended_at);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, TimeSlot>  $entryTimeSlots
     * @return Collection<int, Activity>
     */
    private function trimAroundEntryPeriods(Collection $activities, Collection $entryTimeSlots): Collection
    {
        if ($entryTimeSlots->isEmpty()) {
            return $activities;
        }

        $blockers = new PeriodCollection(...$entryTimeSlots->all());

        return $activities->flatMap(function (Activity $activity) use ($blockers) {
            $activityPeriod = new TimeSlot($activity->started_at, $activity->ended_at);
            $segments = PeriodCollection::make($activityPeriod)->subtract($blockers);

            if (count($segments) === 1 && $segments[0]->equals($activityPeriod)) {
                return [$activity];
            }

            $splits = [];
            foreach ($segments as $segment) {
                $segmentEvents = $activity->events->filter(fn (Event $event) => $this->eventPeriod($event)->overlapsWith($segment));

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
        $split->started_at = Carbon::instance($segment->start());
        $split->ended_at = Carbon::instance($segment->end());
        $split->setRelation('events', $events);
        $split->setRelation('eventType', $activity->eventType);

        return $split;
    }
}
