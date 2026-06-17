<?php

namespace App\DataTransferObjects;

use App\Models\Budget;
use App\Models\User;
use Brick\Math\BigDecimal;

class BudgetMutation
{
    public BigDecimal $startBalance;

    public BigDecimal $usedCredit;

    public BigDecimal $expiredCredit;

    public BigDecimal $usedOutOfBudget;

    public BigDecimal $endBalance;

    public BigDecimal $renewedCredit;

    public Budget $budget;

    public string $customerName;

    public string $budgetTitle;

    public ?User $accountManager;

    public function __construct(Budget $budget)
    {
        $this->budget = $budget;

        $this->startBalance = BigDecimal::of(0);
        $this->usedCredit = BigDecimal::of(0);
        $this->usedOutOfBudget = BigDecimal::of(0);
        $this->expiredCredit = BigDecimal::of(0);
        $this->endBalance = BigDecimal::of(0);
        $this->renewedCredit = BigDecimal::of(0);
    }
}
