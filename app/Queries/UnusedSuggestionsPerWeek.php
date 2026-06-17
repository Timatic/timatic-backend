<?php

namespace App\Queries;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UnusedSuggestionsPerWeek
{
    public static function query(Carbon $startOfWeek, Carbon $endOfWeek): Builder
    {
        return DB::table('entry_suggestions')
            ->selectRaw(
                'entry_suggestions.customer_id,
                        customers.name as customer_name,
                        entry_suggestions.user_id,
                        entry_suggestions.ticket_number,
                        DATE(activities.started_at) as date,
                        ROUND(TIME_TO_SEC(TIMEDIFF(activities.ended_at, activities.started_at))/60) as duration_in_minutes'
            )
            ->join('customers', 'customers.id', '=', 'entry_suggestions.customer_id')
            ->join('activities', 'activities.entry_suggestion_id', '=', 'entry_suggestions.id')
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->whereNull('entry_suggestions.deleted_at')
            // skip all suggestions if there are entries for the same ticket on the same date
            ->leftJoin('entries', function ($join) {
                $join->on(DB::raw('DATE(`entries`.started_at)'), '=', 'entry_suggestions.date')
                    ->on('entries.user_id', '=', 'entry_suggestions.user_id')
                    ->on('entries.ticket_id', '=', 'entry_suggestions.ticket_id');
            })
            ->whereNull('entries.id');
    }
}
