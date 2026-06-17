<?php

namespace App\DataTransferObjects;

class BudgetEntryExportRow
{
    public function __construct(
        public string $entryId,
        public string $date,
        public ?string $ticketNumber = null,
        public ?string $ticketTitle = null,
        public ?string $description = null,
        public ?string $user = null,
        public ?string $overtime = null,
        public ?string $minutesSpent = null,
        public ?string $startedAt = null,
        public ?string $endedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}
}
