<?php

namespace Timatic\Rework\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class ReworkLeaveSlot
{
    public function __construct(
        public int $id,
        public CarbonImmutable $date,
        public float $hours,
        public bool $allDay,
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
