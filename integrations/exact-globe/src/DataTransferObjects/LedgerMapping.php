<?php

namespace Timatic\ExactGlobe\DataTransferObjects;

readonly class LedgerMapping
{
    public function __construct(
        public string $budgetTypeId,
        public string $usageCreditLedgerId,
        public string $usageDebitLedgerId,
        public string $releaseCreditLedgerId,
        public string $releaseDebitLedgerId,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromConfigRow(string $budgetTypeId, array $row): self
    {
        return new self(
            budgetTypeId: $budgetTypeId,
            usageCreditLedgerId: (string) ($row['usage_credit'] ?? ''),
            usageDebitLedgerId: (string) ($row['usage_debit'] ?? ''),
            releaseCreditLedgerId: (string) ($row['release_credit'] ?? ''),
            releaseDebitLedgerId: (string) ($row['release_debit'] ?? ''),
        );
    }
}
