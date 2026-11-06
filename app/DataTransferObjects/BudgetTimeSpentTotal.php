<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

readonly class BudgetTimeSpentTotal
{
    public function __construct(
        public int $budgetId,
        public Carbon|CarbonImmutable $start,
        public Carbon|CarbonImmutable $end,
        public int $remainingMinutes,
        public string $periodUnit,
        public int $periodValue,
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
