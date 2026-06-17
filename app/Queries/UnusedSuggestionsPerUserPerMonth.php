<?php

namespace App\Queries;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

class UnusedSuggestionsPerUserPerMonth
{
    public static function query(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return SumOfUnusedSuggestions::query()
            ->addSelect('entry_suggestions.user_id')
            ->whereBetween('entry_suggestions.date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy('user_id');
    }
}
