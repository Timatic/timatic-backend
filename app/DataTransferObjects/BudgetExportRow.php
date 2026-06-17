<?php

namespace App\DataTransferObjects;

class BudgetExportRow
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $description,
        public readonly ?string $customerId,
        public readonly ?string $customer,
        public readonly ?string $type,
        public readonly ?string $renewalFrequency,
        public readonly ?string $startDate,
        public readonly ?string $expirationDate,
        public readonly ?string $totalHours,
        public readonly ?string $hoursRemaining,
        public readonly ?string $accountManagerName,
        public readonly ?string $changeId,
    ) {}
}
