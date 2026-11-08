<?php

namespace App\Services;

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Activity;
use App\Models\Entry;
use App\Models\Event;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use LogicException;

class ActivityProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    /** @var Collection<int, Activity> */
    private Collection $activities;

    private ActivityOverlap $overlap;

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
                $match = $this->findChainableActivity($event,
                    fn (Activity $a) => $a->customer_id === $customerId
                );

                if ($match) {
                    $this->appendEventToActivity($match, $event);
                } else {
                    $unclaimed->push($event);
                }

                continue;
            }

            $match = $this->findChainableActivity($event,
                fn (Activity $a) => $a->customer_id === $customerId
                    && $a->ticket_number === $event->ticket_number
            );

            if ($match) {
                $this->appendEventToActivity($match, $event);
            } else {
                $this->createActivity($event);
            }
        }

        foreach ($unclaimed as $event) {
            $customerId = $this->attribution->customerId($event);
            $match = $this->findChainableActivity($event,
                fn (Activity $a) => $a->customer_id === $customerId
            );

            if ($match) {
                $this->appendEventToActivity($match, $event);
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
            $this->mergeNeighbours($part, $this->overlap->isFill($event));
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

    /**
     * The nearest activity of the event's own customer decides; work for another customer runs
     * alongside and does not interrupt the chain.
     *
     * @param  Closure(Activity): bool  $matches
     */
    private function findChainableActivity(Event $event, Closure $matches): ?Activity
    {
        $eventStart = $event->effectiveStart();
        $eventEnd = $event->ended_at->copy();
        $ownWork = $this->activities
            ->reject(fn (Activity $a) => $this->overlap->isOtherCustomer($this->attribution->customerId($event), $a->customer_id));

        $precedingActivity = $ownWork
            ->filter(fn (Activity $a) => $a->ended_at->isBefore($eventEnd)
                && $a->ended_at->copy()->addMinutes(self::CHAIN_GAP_MINUTES)->isAfter($eventStart)
            )
            ->sortBy(fn (Activity $a) => $a->ended_at->getTimestamp())
            ->last();

        if ($precedingActivity && $matches($precedingActivity)) {
            return $precedingActivity;
        }

        $followingActivity = $ownWork
            ->filter(fn (Activity $a) => $a->started_at->isAfter($eventStart)
                && $a->started_at->copy()->subMinutes(self::CHAIN_GAP_MINUTES)->isBefore($eventEnd)
            )
            ->sortBy(fn (Activity $a) => $a->started_at->getTimestamp())
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

    private function appendEventToActivity(Activity $activity, Event $event): void
    {
        $alreadyCovered = $this->overlap->period($activity->started_at, $activity->ended_at);
        $activity->events->push($event);
        $effectiveStart = $event->effectiveStart();

        if ($effectiveStart->isBefore($activity->started_at)) {
            $activity->started_at = Carbon::instance($effectiveStart);
        }

        if ($event->ended_at->isAfter($activity->ended_at)) {
            $activity->ended_at = $event->ended_at;
        }

        $parts = $this->overlap->reduceOverlap($activity, $this->activities, $this->overlap->isFill($event), $alreadyCovered);
        $position = $this->activities->search($activity, true);

        if ($position === false) {
            throw new LogicException('An event can only be appended to a projected activity.');
        }

        $this->activities->splice($position, 1, $parts);

        foreach ($parts as $part) {
            $this->mergeNeighbours($part, $this->overlap->isFill($event));
        }
    }

    /**
     * Events are projected by weight before time, so a widened activity can come to lie next to
     * an earlier projected activity of the same work item; they are one piece of work. A fill
     * activity does not merge across any claim, or it would cover the work it gave way to again.
     *
     * ponytail: every merge rescans all activities and the gap check scans them again, O(n²) per
     * call and O(n³) per day; fine for a day's events, index activities by customer and time if a
     * user-day ever holds thousands.
     */
    private function mergeNeighbours(Activity $activity, bool $isFill): void
    {
        // An earlier part of the same activity may already have absorbed this one.
        if (! $this->activities->contains(fn (Activity $other) => $other === $activity)) {
            return;
        }

        while ($neighbour = $this->activities->first(fn (Activity $other) => $this->isMergeable($activity, $other, $isFill))) {
            [$kept, $absorbed] = $this->activities->first(fn (Activity $other) => $other === $activity || $other === $neighbour) === $activity
                ? [$activity, $neighbour]
                : [$neighbour, $activity];

            $kept->setRelation('events', $kept->events->concat($absorbed->events)->unique(null, true)->values());
            $kept->started_at = $kept->started_at->min($absorbed->started_at);
            $kept->ended_at = $kept->ended_at->max($absorbed->ended_at);
            $this->activities = $this->activities->reject(fn (Activity $other) => $other === $absorbed)->values();
            $activity = $kept;
        }
    }

    private function isMergeable(Activity $activity, Activity $other, bool $isFill): bool
    {
        if ($other === $activity
            || $activity->customer_id === null
            || $other->customer_id !== $activity->customer_id
            || $other->ticket_number !== $activity->ticket_number) {
            return false;
        }

        $gapStart = $activity->ended_at->min($other->ended_at);
        $gapEnd = $activity->started_at->max($other->started_at);

        if ($gapStart->copy()->addMinutes(self::CHAIN_GAP_MINUTES)->lte($gapEnd)) {
            return false;
        }

        return ! $this->overlap->isClaimedBetween($activity, $other, $this->activities, $isFill);
    }
}
