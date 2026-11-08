<?php

namespace App\Services;

use App\DataTransferObjects\PreviousSequenceBooking;
use App\Models\Activity;
use App\Models\Entry;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use LogicException;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

class ActivityProjector
{
    private const CHAIN_GAP_MINUTES = 15;

    /** Event types at or above this weight (browser focus) are weak evidence that only fills gaps. */
    private const FILL_WEIGHT = 100;

    /** @var Collection<int, Activity> */
    private Collection $activities;

    /** @var Collection<int, Entry> */
    private Collection $entries;

    /** @var Collection<int, PreviousSequenceBooking> */
    private Collection $previousSequenceBookings;

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
        $this->entries = $entries;
        $this->previousSequenceBookings = $previousSequenceBookings;

        $sorted = $events->sort(function (Event $a, Event $b) {
            return ((int) $a->eventType?->weight <=> (int) $b->eventType?->weight)
                ?: $a->effectiveStart()->getTimestamp() <=> $b->effectiveStart()->getTimestamp()
                ?: $a->ended_at->getTimestamp() <=> $b->ended_at->getTimestamp()
                ?: $a->id <=> $b->id;
        })->values();

        [$fillEvents, $workEvents] = $sorted->partition(fn (Event $event) => $this->isFill($event))->all();

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
            $customerId = $this->customerId($event);

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
            $customerId = $this->customerId($event);
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
        $newActivityPartsWithoutOverlap = $this->reduceOverlap($newActivity, $this->isFill($event));
        $this->activities->push(...$newActivityPartsWithoutOverlap);

        foreach ($newActivityPartsWithoutOverlap as $part) {
            $this->mergeNeighbours($part, $this->isFill($event));
        }

        // Fully covered, the event has no time of its own left, but it still underlines the work of
        // the same customer that covers it.
        $customerId = $this->customerId($event);

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
     * Work for another known customer may run alongside (multitasking); within one customer the
     * stronger evidence claims the time. A fill signal only gets what all entries and activities left.
     * Time the activity already covered stays its own.
     *
     * @return array<int, Activity>
     */
    private function reduceOverlap(Activity $activity, bool $isFill, ?Period $alreadyCovered = null): array
    {
        $claimed = new PeriodCollection(
            ...$this->entries
                ->filter(fn (Entry $entry) => $isFill || ! $this->isOtherCustomer($activity->customer_id, $entry->customer_id))
                ->map(fn (Entry $entry) => $this->period($entry->started_at, $entry->ended_at))
                ->all(),
            ...$this->activities
                ->filter(fn (Activity $other) => $other !== $activity && ($isFill || ! $this->isOtherCustomer($activity->customer_id, $other->customer_id)))
                ->map(fn (Activity $other) => $this->period($other->started_at, $other->ended_at))
                ->all(),
        );

        if ($alreadyCovered !== null) {
            $claimed = $claimed->subtract($alreadyCovered);
        }

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

            $events = $activity->events->filter(
                fn (Event $event) => $event->effectiveStart()->lt($segment->end()) && $event->ended_at->gt($segment->start())
            )->values();
            $partialActivity->setRelation('events', $events);
            $parts[] = $partialActivity;
        }

        return $parts;
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
            ->reject(fn (Activity $a) => $this->isOtherCustomer($this->customerId($event), $a->customer_id));

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
        $activity->budget_id = $this->budgetId($event);
        $activity->ticket_id = $event->ticket_id;
        $activity->ticket_number = $event->ticket_number;
        $activity->ticket_type = $event->ticket_type;
        $activity->title = $event->title;
        $activity->description = $event->description;
        $activity->customer_id = $this->customerId($event);
        $activity->is_internal = $this->isInternal($event);
        $activity->started_at = Carbon::instance($event->effectiveStart());
        $activity->ended_at = $event->ended_at;
        $activity->setRelation('events', collect([$event]));

        return $activity;
    }

    private function appendEventToActivity(Activity $activity, Event $event): void
    {
        $alreadyCovered = $this->period($activity->started_at, $activity->ended_at);
        $activity->events->push($event);
        $effectiveStart = $event->effectiveStart();

        if ($effectiveStart->isBefore($activity->started_at)) {
            $activity->started_at = Carbon::instance($effectiveStart);
        }

        if ($event->ended_at->isAfter($activity->ended_at)) {
            $activity->ended_at = $event->ended_at;
        }

        $parts = $this->reduceOverlap($activity, $this->isFill($event), $alreadyCovered);
        $position = $this->activities->search($activity, true);

        if ($position === false) {
            throw new LogicException('An event can only be appended to a projected activity.');
        }

        $this->activities->splice($position, 1, $parts);

        foreach ($parts as $part) {
            $this->mergeNeighbours($part, $this->isFill($event));
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

        $unionStart = $activity->started_at->min($other->started_at);
        $unionEnd = $activity->ended_at->max($other->ended_at);

        return ! $this->entries->contains(fn (Entry $entry) => ($isFill || ! $this->isOtherCustomer($activity->customer_id, $entry->customer_id))
            && $entry->started_at->lt($unionEnd) && $entry->ended_at->gt($unionStart))
            && ($gapEnd->lte($gapStart) || ! $this->activities->contains(fn (Activity $claim) => $claim !== $activity && $claim !== $other
                && ($isFill || ! $this->isOtherCustomer($activity->customer_id, $claim->customer_id))
                && $claim->started_at->lt($gapEnd) && $claim->ended_at->gt($gapStart)));
    }

    private function isFill(Event $event): bool
    {
        return (int) $event->eventType?->weight >= self::FILL_WEIGHT;
    }

    /**
     * An unknown customer is not another customer: it may be the same work.
     */
    private function isOtherCustomer(int|string|null $customerId, int|string|null $otherCustomerId): bool
    {
        return $customerId !== null && $otherCustomerId !== null && (string) $customerId !== (string) $otherCustomerId;
    }

    /**
     * An event without a mapping of its own is booked like the last entry on an
     * earlier occurrence of its sequence, e.g. the previous instance of a recurring
     * meeting. The event itself stays as it came in; only the activity learns.
     */
    private function customerId(Event $event): ?string
    {
        return $event->customer_id ?? $this->previousBookingOf($event)?->customerId;
    }

    private function budgetId(Event $event): ?int
    {
        if ($event->budget_id !== null) {
            return $event->budget_id;
        }

        $previousBooking = $this->previousBookingOf($event);

        if ($previousBooking === null || $previousBooking->customerId !== $this->customerId($event)) {
            return null;
        }

        return $previousBooking->budgetId;
    }

    private function isInternal(Event $event): ?bool
    {
        if ($event->customer_id !== null) {
            return $event->is_internal;
        }

        $previousBooking = $this->previousBookingOf($event);

        return $previousBooking === null ? $event->is_internal : $previousBooking->isInternal;
    }

    private function previousBookingOf(Event $event): ?PreviousSequenceBooking
    {
        return $this->previousSequenceBookings
            ->where('sourceId', $event->source_id)
            ->where('externalId', $event->external_id)
            ->sortByDesc('startedAt')
            ->first();
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
