<?php

namespace App\Queries;

use App\Models\Entry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class UserEntriesInDay
{
    /**
     * @return Builder<Entry>
     */
    public static function query(int $userId, CarbonInterface $day): Builder
    {
        return Entry::query()
            ->where('user_id', $userId)
            ->where('started_at', '<', $day->copy()->addDay())
            ->where('ended_at', '>', $day);
    }
}
