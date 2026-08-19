<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class Period
{
    public function __construct(
        public CarbonInterface $startedAt,
        public CarbonInterface $endedAt,
    ) {}

    public function overlaps(Period $other): bool
    {
        return $this->startedAt->lessThan($other->endedAt)
            && $this->endedAt->greaterThan($other->startedAt);
    }

    public function covers(Period $other): bool
    {
        return $this->startedAt->lessThanOrEqualTo($other->startedAt)
            && $this->endedAt->greaterThanOrEqualTo($other->endedAt);
    }

    /**
     * @param  Collection<int, Period>  $blockers
     * @return Collection<int, Period>
     */
    public function subtract(Collection $blockers): Collection
    {
        /** @var Collection<int, Period> $segments */
        $segments = collect([$this]);

        foreach ($blockers->sortBy('startedAt') as $blocker) {
            $segments = $segments->flatMap(function (Period $segment) use ($blocker) {
                if (! $segment->overlaps($blocker)) {
                    return [$segment];
                }

                $remaining = [];
                if ($blocker->startedAt->greaterThan($segment->startedAt)) {
                    $remaining[] = new Period($segment->startedAt, $blocker->startedAt);
                }
                if ($blocker->endedAt->lessThan($segment->endedAt)) {
                    $remaining[] = new Period($blocker->endedAt, $segment->endedAt);
                }

                return $remaining;
            });
        }

        return $segments->values();
    }
}
