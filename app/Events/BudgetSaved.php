<?php

namespace App\Events;

use App\Events\Contracts\HasBudget;
use App\Models\Budget;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BudgetSaved implements HasBudget
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(private Budget $budget) {}

    public function getBudget(): Budget
    {
        return $this->budget;
    }
}
