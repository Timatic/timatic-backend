<?php

namespace App\DataTransferObjects;

readonly class BudgetExportRow
{
    public function __construct(
        public int $id,
        public ?string $description,
        public ?string $customerId,
        public ?string $customer,
        public ?string $type,
        public ?string $renewalFrequency,
        public ?string $startDate,
        public ?string $expirationDate,
        public ?string $totalHours,
        public ?string $hoursRemaining,
        public ?string $accountManagerName,
        public ?string $changeId,
    ) {}
}
