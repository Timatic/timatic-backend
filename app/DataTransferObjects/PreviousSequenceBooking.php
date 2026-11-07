<?php

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;

readonly class PreviousSequenceBooking
{
    public function __construct(
        public string $sourceId,
        public string $externalId,
        public string $customerId,
        public ?int $budgetId,
        public bool $isInternal,
        public CarbonInterface $startedAt,
    ) {}
}
