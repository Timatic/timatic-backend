<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

readonly class BudgetShareLink
{
    public function __construct(
        public int $budgetId,
        public string $token,
    ) {}
}
