<?php

namespace App\Queries;

use App\Models\Entry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HoursPerUserPerMonth
{
    /**
     * @return Builder<Entry>
     */
    public static function query(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Entry::query()
            ->select(DB::raw(
                <<<'QUERY'
                user_id, user_full_name, user_email,
                SUM(minutes_spent)/60 AS total,
                SUM(IF(entry_suggestion_id IS NOT NULL, minutes_spent, 0))/60 AS based_on_suggestions,
                SUM(IF(is_internal = 1 AND customer_id IN (50340, 10641), minutes_spent, 0))/60 AS internal_tenant,
                SUM(IF(is_internal = 1 AND customer_id NOT IN (50340, 10641), minutes_spent, 0))/60 AS internal_customers,
                SUM(IF(budget_id IS NOT NULL, minutes_spent, 0))/60 AS on_budgets,
                SUM(IF(budget_id IS NULL AND is_internal = 0, minutes_spent, 0))/60 AS paid_per_hour
                QUERY
            ))
            ->whereBetween('started_at', [$start, $end])
            ->with('budget')
            ->orderBy('user_full_name')
            ->groupBy('user_id', 'user_full_name', 'user_email');
    }
}
