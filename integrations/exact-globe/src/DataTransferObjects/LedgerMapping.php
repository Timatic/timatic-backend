<?php

namespace Timatic\ExactGlobe\DataTransferObjects;

readonly class LedgerMapping
{
    public function __construct(
        public string $budgetTypeId,
        public string $verbruikCreditLedgerId,
        public string $verbruikDebitLedgerId,
        public string $vrijvalCreditLedgerId,
        public string $vrijvalDebitLedgerId,
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromConfigRow(string $budgetTypeId, array $row): self
    {
        return new self(
            budgetTypeId: $budgetTypeId,
            verbruikCreditLedgerId: (string) ($row['verbruik_credit'] ?? ''),
            verbruikDebitLedgerId: (string) ($row['verbruik_debit'] ?? ''),
            vrijvalCreditLedgerId: (string) ($row['vrijval_credit'] ?? ''),
            vrijvalDebitLedgerId: (string) ($row['vrijval_debit'] ?? ''),
        );
    }
}
