<?php

namespace Timatic\Nmbrs\DataTransferObjects;

use Carbon\CarbonImmutable;

class NmbrsLeaveRequest
{
    public function __construct(
        public readonly string $leaveRequestId,
        public readonly string $employeeId,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly float $hours,
    ) {}

    public function isActiveToday(): bool
    {
        $today = CarbonImmutable::today('Europe/Amsterdam');

        return $this->startDate->lte($today) && $this->endDate->gte($today);
    }

    public function weekdayCount(): int
    {
        $count = 0;
        $current = $this->startDate->startOfDay();
        $end = $this->endDate->startOfDay();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current = $current->addDay();
        }

        return max(1, $count);
    }

    public function minutesPerDay(): int
    {
        return (int) floor($this->hours * 60 / $this->weekdayCount());
    }
}
