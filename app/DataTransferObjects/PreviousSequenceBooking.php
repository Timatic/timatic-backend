<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;

class PreviousSequenceBooking
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $externalId,
        public readonly string $customerId,
        public readonly ?int $budgetId,
        public readonly bool $isInternal,
        public readonly CarbonInterface $startedAt,
    ) {}
}
