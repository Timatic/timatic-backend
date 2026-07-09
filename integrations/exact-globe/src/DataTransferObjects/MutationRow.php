<?php

namespace Timatic\ExactGlobe\DataTransferObjects;

use Brick\Math\BigDecimal;

readonly class MutationRow
{
    public function __construct(
        public int $index,
        public string $description,
        public BigDecimal $amount,
        public ?string $customerId,
        public string $budgetTypeId,
        public int $budgetId,
        public bool $credit,
    ) {}
}
