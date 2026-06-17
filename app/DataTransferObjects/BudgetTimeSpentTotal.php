<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class BudgetTimeSpentTotal
{
    public function __construct(
        public readonly int $budgetId,
        public readonly Carbon|CarbonImmutable $start,
        public readonly Carbon|CarbonImmutable $end,
        public readonly int $remainingMinutes,
        public readonly string $periodUnit,
        public readonly int $periodValue,
    ) {}

    public function getId(): string
    {
        return base64_encode(
            $this->start->toDateTimeString().
            $this->end->toDateTimeString().
            $this->periodUnit.
            $this->budgetId
        );
    }
}
