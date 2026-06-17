<?php

namespace App\Events\Contracts;

use App\Models\Budget;

interface HasBudget
{
    public function getBudget(): ?Budget;
}
