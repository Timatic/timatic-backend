<?php

namespace App\Services;

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Activity;
use App\Models\Entry;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ActivityProjector
{
    /** @var Collection<int, Activity> */
    private Collection $activities;

    private ActivityOverlap $overlap;

    private ActivityChainer $chainer;

    private EventAttribution $attribution;

    public function __construct()
    {
        $this->activities = collect();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Entry>  $entries
     * @param  Collection<int, PreviousSequenceBooking>  $previousSequenceBookings
     * @return Collection<int, Activity>
     */
    public function project(Collection $events, Collection $entries, Collection $previousSequenceBookings = new Collection): Collection
    {
        $this->overlap = new ActivityOverlap($entries);
        $this->chainer = new ActivityChainer($this->overlap);
        $this->attribution = new EventAttribution($previousSequenceBookings);

        $sorted = $events->sort(function (Event $a, Event $b) {
            return ((int) $a->eventType?->weight <=> (int) $b->eventType?->weight)
                ?: $a->effectiveStart()->getTimestamp() <=> $b->effectiveStart()->getTimestamp()
                ?: $a->ended_at->getTimestamp() <=> $b->ended_at->getTimestamp()
                ?: $a->id <=> $b->id;
        })->values();

        [$fillEvents, $workEvents] = $sorted->partition(fn (Event $event) => $this->overlap->isFill($event))->all();

        // Fill signals go after the held-back ticketless work too, so they only get what all work left.
        $this->projectEvents($workEvents);
        $this->projectEvents($fillEvents);

        return $this->activities;
    }

    /**
     * @param  Collection<int, Event>  $events
     */
    private function projectEvents(Collection $events): void
    {
        /** @var Collection<int, Event> $unclaimed */
        $unclaimed = collect();

        foreach ($events as $event) {
            $customerId = $this->attribution->customerId($event);

            if ($customerId === null) {
                $this->createActivity($event);

                continue;
            }

            if ($event->ticket_number === null) {
                $match = $this->chainer->findChainableActivity($event, $customerId, $this->activities,
                    fn (Activity $a) => $a->customer_id === $customerId
                );

                if ($match) {
                    $this->chainer->appendEventToActivity($match, $event, $this->activities);
                } else {
                    $unclaimed->push($event);
                }

                continue;
            }

            $match = $this->chainer->findChainableActivity($event, $customerId, $this->activities,
                fn (Activity $a) => $a->customer_id === $customerId
                    && $a->ticket_number === $event->ticket_number
            );

            if ($match) {
                $this->chainer->appendEventToActivity($match, $event, $this->activities);
            } else {
                $this->createActivity($event);
            }
        }

        foreach ($unclaimed as $event) {
            $customerId = $this->attribution->customerId($event);
            $match = $this->chainer->findChainableActivity($event, $customerId, $this->activities,
                fn (Activity $a) => $a->customer_id === $customerId
            );

            if ($match) {
                $this->chainer->appendEventToActivity($match, $event, $this->activities);
            } else {
                $this->createActivity($event);
            }
        }
    }

    private function createActivity(Event $event): void
    {
        $newActivity = $this->activityFromEvent($event);
        $newActivityPartsWithoutOverlap = $this->overlap->reduceOverlap($newActivity, $this->activities, $this->overlap->isFill($event));
        $this->activities->push(...$newActivityPartsWithoutOverlap);

        foreach ($newActivityPartsWithoutOverlap as $part) {
            $this->chainer->mergeNeighbours($part, $this->activities, $this->overlap->isFill($event));
        }

        // Fully covered, the event has no time of its own left, but it still underlines the work of
        // the same customer that covers it.
        $customerId = $this->attribution->customerId($event);

        if ($newActivityPartsWithoutOverlap === [] && $customerId !== null) {
            $coveringActivities = $this->activities
                ->filter(fn (Activity $activity) => $activity->customer_id === $customerId
                    && $activity->started_at->lt($event->ended_at)
                    && $activity->ended_at->gt($event->effectiveStart()));

            ($coveringActivities->first(fn (Activity $activity) => $activity->ticket_number === null
                || $event->ticket_number === null
                || $activity->ticket_number === $event->ticket_number) ?? $coveringActivities->first())
                ?->events->push($event);
        }
    }

    private function activityFromEvent(Event $event): Activity
    {
        $activity = new Activity;
        $activity->source_id = $event->source_id;
        $activity->user_id = $event->user_id;
        $activity->budget_id = $this->attribution->budgetId($event);
        $activity->ticket_id = $event->ticket_id;
        $activity->ticket_number = $event->ticket_number;
        $activity->ticket_type = $event->ticket_type;
        $activity->title = $event->title;
        $activity->description = $event->description;
        $activity->customer_id = $this->attribution->customerId($event);
        $activity->is_internal = $this->attribution->isInternal($event);
        $activity->started_at = Carbon::instance($event->effectiveStart());
        $activity->ended_at = $event->ended_at;
        $activity->setRelation('events', collect([$event]));

        return $activity;
    }
}
