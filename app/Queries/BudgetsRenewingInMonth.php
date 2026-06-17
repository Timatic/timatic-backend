<?php

namespace App\Queries;

use App\Models\Budget;
use App\Models\BudgetVersion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BudgetsRenewingInMonth
{
    /**
     * @return Builder<Budget>
     */
    public static function query(Carbon $month): Builder
    {
        return Budget::query()
            ->where(function ($query) use ($month) {
                $query->where('renewal_frequency', '=', 'monthly')
                    ->orWhere(function ($query) use ($month) {
                        $query->where('renewal_frequency', '=', 'yearly')
                            ->whereRaw('MONTH(DATE_SUB(started_at, INTERVAL 1 DAY)) = ?', [$month->month]);
                    })
                    ->orWhere(function ($query) use ($month) {
                        $query->whereNull('renewal_frequency')
                            ->whereYear('ended_at', $month->year)
                            ->whereMonth('ended_at', $month->month);
                    });
            })
            ->whereIn(
                'id',
                BudgetVersion::query()
                    // select the correct version
                    ->where('effective_from', '<=', $month)
                    ->where(function ($query) use ($month) {
                        $query->where('effective_to', '>', $month)
                            ->orWhereNull('effective_to');
                    })
                    // check if it has minutes and a price
                    ->where('initial_minutes', '>', 0)
                    ->where('total_price', '>', 0)
                    ->pluck('budget_id')
            )
            ->where('ended_at', '>', $month->clone()->firstOfMonth())
            ->where('started_at', '<=', $month->clone()->firstOfMonth())
            ->orderBy('id');
    }
}
