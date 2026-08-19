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
        return $this->chainEventsIntoGroups($events)
            ->map(fn (EventGroup $group) => $this->activityFromGroup($group, $group->period(), $group->events))
            ->values();
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
}
