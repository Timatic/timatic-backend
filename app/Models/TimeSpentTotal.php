<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class TimeSpentTotal
{
    public function __construct(
        public readonly Carbon|CarbonImmutable $start,
        public readonly Carbon|CarbonImmutable $end,
        public readonly int $internalMinutes,
        public readonly int $billableMinutes,
        public readonly string $periodUnit,
        public readonly int $periodValue,
    ) {}

    public function getId(): string
    {
        return base64_encode(
            $this->start->toDateTimeString().
            $this->end->toDateTimeString().
            $this->periodUnit
        );
    }
}
