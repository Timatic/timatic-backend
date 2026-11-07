<?php

namespace Timatic\Nmbrs\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class NmbrsLeaveRequest
{
    public function __construct(
        public string $leaveRequestId,
        public string $employeeId,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public float $hours,
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
