<?php

namespace App\Queries;

use App\Models\EntrySuggestion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class UserDismissedSuggestionsOnDate
{
    /**
     * @return Builder<EntrySuggestion>
     */
    public static function query(int $userId, CarbonInterface $day): Builder
    {
        return EntrySuggestion::onlyTrashed()
            ->whereDoesntHave('entry')
            ->where('user_id', $userId)
            ->where('date', $day->toDateString());
    }
}
