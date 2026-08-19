<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class TimeSlot
{
    public function __construct(
        public CarbonInterface $startedAt,
        public CarbonInterface $endedAt,
    ) {}

    public function overlaps(TimeSlot $other): bool
    {
        return $this->startedAt->lessThan($other->endedAt)
            && $this->endedAt->greaterThan($other->startedAt);
    }

    public function covers(TimeSlot $other): bool
    {
        return $this->startedAt->lessThanOrEqualTo($other->startedAt)
            && $this->endedAt->greaterThanOrEqualTo($other->endedAt);
    }

    /**
     * @param  Collection<int, TimeSlot>  $blockers
     * @return Collection<int, TimeSlot>
     */
    public function subtract(Collection $blockers): Collection
    {
        /** @var Collection<int, TimeSlot> $segments */
        $segments = collect([$this]);

        foreach ($blockers->sortBy('startedAt') as $blocker) {
            $segments = $segments->flatMap(function (TimeSlot $segment) use ($blocker) {
                if (! $segment->overlaps($blocker)) {
                    return [$segment];
                }

                $remaining = [];
                if ($blocker->startedAt->greaterThan($segment->startedAt)) {
                    $remaining[] = new TimeSlot($segment->startedAt, $blocker->startedAt);
                }
                if ($blocker->endedAt->lessThan($segment->endedAt)) {
                    $remaining[] = new TimeSlot($blocker->endedAt, $segment->endedAt);
                }

                return $remaining;
            });
        }

        return $segments->values();
    }
}
