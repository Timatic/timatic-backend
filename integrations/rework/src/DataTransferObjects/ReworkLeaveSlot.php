<?php

namespace Timatic\Rework\DataTransferObjects;

use Carbon\CarbonImmutable;

class ReworkLeaveSlot
{
    public function __construct(
        public readonly int $id,
        public readonly CarbonImmutable $date,
        public readonly float $hours,
        public readonly bool $allDay,
    ) {}

    public function isToday(): bool
    {
        return $this->date->isToday();
    }

    public function minutesForDay(): int
    {
        return (int) floor($this->hours * 60);
    }
}
