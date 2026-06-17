<?php

namespace App\Services;

use App\Models\DailyProgress;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyProgressCalculator
{
    public function calculate(DailyProgress $progress, int $workHours = 8): DailyProgress
    {
        /** @var Carbon $startOfDay */
        $startOfDay = $progress->getDate()->clone()->startOfDay()->tz('utc');
        /** @var Carbon $endOfDay */
        $endOfDay = $progress->getDate()->clone()->endOfDay()->tz('utc');

        $workedSeconds = Entry::query()
            ->where('user_id', $progress->getUserId())
            ->where('started_at', '>=', $startOfDay)
            ->where('started_at', '<=', $endOfDay)
            ->sum(DB::raw('time_to_sec(TIMEDIFF(ended_at, started_at))'));

        $progress->setProgress((int) round((($workedSeconds / 60 / 60) / $workHours) * 100));

        return $progress;
    }
}
