<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Entry;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\PeriodCollection;
use Spatie\Period\Precision;

/**
 * Work for another known customer may run alongside (multitasking); within one customer the
 * stronger evidence claims the time. A fill signal only gets what all entries and activities left.
 */
class ActivityOverlap
{
    /** Event types at or above this weight (browser focus) are weak evidence that only fills gaps. */
    private const FILL_WEIGHT = 100;

    /**
     * @param  Collection<int, Entry>  $entries
     */
    public function __construct(private readonly Collection $entries) {}

    public function isFill(Event $event): bool
    {
        return (int) $event->eventType?->weight >= self::FILL_WEIGHT;
    }

    /**
     * An unknown customer is not another customer: it may be the same work.
     */
    public function isOtherCustomer(int|string|null $customerId, int|string|null $otherCustomerId): bool
    {
        return $customerId !== null && $otherCustomerId !== null && (string) $customerId !== (string) $otherCustomerId;
    }

    /**
     * Time the activity already covered stays its own.
     *
     * @param  Collection<int, Activity>  $activities
     * @return array<int, Activity>
     */
    public function reduceOverlap(Activity $activity, Collection $activities, bool $isFill, ?Period $alreadyCovered = null): array
    {
        $claimed = new PeriodCollection(
            ...$this->entries
                ->filter(fn (Entry $entry) => $isFill || ! $this->isOtherCustomer($activity->customer_id, $entry->customer_id))
                ->map(fn (Entry $entry) => $this->period($entry->started_at, $entry->ended_at))
                ->all(),
            ...$activities
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
     * Joining two activities of one customer would claim the time they span: an entry of that
     * customer in it, or other work of that customer in the gap between them, keeps them apart.
     * A fill activity gave way to all work, so any entry or activity keeps its parts apart.
     *
     * @param  Collection<int, Activity>  $activities
     */
    public function isClaimedBetween(Activity $activity, Activity $other, Collection $activities, bool $isFill): bool
    {
        $gapStart = $activity->ended_at->min($other->ended_at);
        $gapEnd = $activity->started_at->max($other->started_at);
        $unionStart = $activity->started_at->min($other->started_at);
        $unionEnd = $activity->ended_at->max($other->ended_at);

        return $this->entries->contains(fn (Entry $entry) => ($isFill || ! $this->isOtherCustomer($activity->customer_id, $entry->customer_id))
            && $entry->started_at->lt($unionEnd) && $entry->ended_at->gt($unionStart))
            || ($gapEnd->gt($gapStart) && $activities->contains(fn (Activity $claim) => $claim !== $activity && $claim !== $other
                && ($isFill || ! $this->isOtherCustomer($activity->customer_id, $claim->customer_id))
                && $claim->started_at->lt($gapEnd) && $claim->ended_at->gt($gapStart)));
    }

    public function period(CarbonInterface $start, CarbonInterface $end): Period
    {
        return new Period(
            DateTimeImmutable::createFromInterface($start),
            DateTimeImmutable::createFromInterface($end),
            Precision::SECOND(),
            Boundaries::EXCLUDE_END(),
        );
    }
}
