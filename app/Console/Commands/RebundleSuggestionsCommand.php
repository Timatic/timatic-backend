<?php

namespace App\Console\Commands;

use App\Jobs\RebuildUserDay;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RebundleSuggestionsCommand extends Command
{
    protected $signature = 'timatic:rebundle-suggestions
        {--user= : Only rebuild suggestions of this user id}
        {--from= : Rebuild suggestions from this date (Y-m-d), defaults to 90 days ago}
        {--to= : Rebuild suggestions up to this date (Y-m-d), defaults to today}';

    protected $description = 'Rebuild the activities and suggestions for every date in the given range';

    public function handle(): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : now()->subMonth()->startOfMonth();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->startOfDay() : now()->startOfDay();

        $userIds = Event::query()
            ->where('ended_at', '>=', $from)
            ->where('ended_at', '<', $to->copy()->addDay())
            ->when($this->option('user'), fn ($query, $user) => $query->where('user_id', $user))
            ->distinct()
            ->pluck('user_id');

        $count = 0;

        foreach ($userIds as $userId) {
            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                RebuildUserDay::dispatchSync((int) $userId, $date->toDateString());
                $count++;
            }
        }

        $this->info(sprintf('Rebuilt %d user-days.', $count));

        return self::SUCCESS;
    }
}
