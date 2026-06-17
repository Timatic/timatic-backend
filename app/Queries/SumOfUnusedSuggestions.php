<?php

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SumOfUnusedSuggestions
{
    public static function query(): Builder
    {
        return DB::query()
            ->select(
                DB::raw('
                SUM(TIME_TO_SEC(TIMEDIFF(activities.ended_at, activities.started_at)))/60/60 AS unused_suggestions
                ')
            )
            ->from('entry_suggestions')
            ->join('activities', 'activities.entry_suggestion_id', '=', 'entry_suggestions.id')
            ->whereNotNull('deleted_at');
    }
}
