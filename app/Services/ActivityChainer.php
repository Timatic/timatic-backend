<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Chains events and activities of one piece of work that lie within a short gap of each other.
 * The activities collection is the projector's; it is changed in place.
 */
class ActivityChainer
{
    private const CHAIN_GAP_MINUTES = 15;

    public function __construct(private readonly ActivityOverlap $overlap) {}

    /**
     * The nearest activity of the event's own customer decides; work for another customer runs
     * alongside and does not interrupt the chain.
     *
     * @param  Collection<int, Activity>  $activities
     * @param  Closure(Activity): bool  $matches
     */
    public function findChainableActivity(Event $event, string $customerId, Collection $activities, Closure $matches): ?Activity
    {
        $eventStart = $event->effectiveStart();
        $eventEnd = $event->ended_at->copy();
        $ownWork = $activities
            ->reject(fn (Activity $a) => $this->overlap->isOtherCustomer($customerId, $a->customer_id));

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

    /**
     * @param  Collection<int, Activity>  $activities
     */
    public function appendEventToActivity(Activity $activity, Event $event, Collection $activities): void
    {
        $coveredFrom = $activity->started_at->copy();
        $coveredUntil = $activity->ended_at->copy();
        $activity->events->push($event);
        $effectiveStart = $event->effectiveStart();

        if ($effectiveStart->isBefore($activity->started_at)) {
            $activity->started_at = Carbon::instance($effectiveStart);
        }

        if ($event->ended_at->isAfter($activity->ended_at)) {
            $activity->ended_at = $event->ended_at;
        }

        $parts = $this->overlap->reduceOverlap($activity, $activities, $this->overlap->isFill($event), $coveredFrom, $coveredUntil);
        $position = $activities->search($activity, true);

        if ($position === false) {
            throw new LogicException('An event can only be appended to a projected activity.');
        }

        $activities->splice($position, 1, $parts);

        foreach ($parts as $part) {
            $this->mergeNeighbours($part, $activities, $this->overlap->isFill($event));
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
     *
     * @param  Collection<int, Activity>  $activities
     */
    public function mergeNeighbours(Activity $activity, Collection $activities, bool $isFill): void
    {
        // An earlier part of the same activity may already have absorbed this one.
        if (! $activities->contains(fn (Activity $other) => $other === $activity)) {
            return;
        }

        while ($neighbour = $activities->first(fn (Activity $other) => $this->isMergeable($activity, $other, $activities, $isFill))) {
            [$kept, $absorbed] = $activities->first(fn (Activity $other) => $other === $activity || $other === $neighbour) === $activity
                ? [$activity, $neighbour]
                : [$neighbour, $activity];

            $kept->setRelation('events', $kept->events->concat($absorbed->events)->unique(null, true)->values());
            $kept->started_at = $kept->started_at->min($absorbed->started_at);
            $kept->ended_at = $kept->ended_at->max($absorbed->ended_at);
            $absorbedPosition = $activities->search($absorbed, true);

            if ($absorbedPosition !== false) {
                $activities->splice($absorbedPosition, 1);
            }

            $activity = $kept;
        }
    }

    /**
     * @param  Collection<int, Activity>  $activities
     */
    private function isMergeable(Activity $activity, Activity $other, Collection $activities, bool $isFill): bool
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

        return ! $this->overlap->isClaimedBetween($activity, $other, $activities, $isFill);
    }
}
