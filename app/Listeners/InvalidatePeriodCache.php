<?php

namespace App\Listeners;

use App\Events\BudgetSaved;
use App\Events\EntrySaved;
use Illuminate\Support\Facades\Cache;

class InvalidatePeriodCache
{
    public function handle(BudgetSaved|EntrySaved $event): void
    {
        $budget = $event->getBudget();

        if ($budget === null) {
            return;
        }

        Cache::tags(['budget.'.$budget->id])->flush();
    }
}
