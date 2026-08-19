<?php

namespace App\Queries;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class UserDayEvents
{
    /**
     * @return Builder<Event>
     */
    public static function query(int $userId, CarbonInterface $day): Builder
    {
        return Event::query()
            ->with('eventType')
            ->where('user_id', $userId)
            ->where('ended_at', '>=', $day)
            ->where('ended_at', '<', $day->copy()->addDay());
    }
}
