<?php

namespace App\Console\Commands;

use App\Jobs\RebuildUserDay;
use App\Models\EntrySuggestion;
use Illuminate\Console\Command;

class RebundleSuggestionsCommand extends Command
{
    protected $signature = 'timatic:rebundle-suggestions
        {--user= : Only rebuild suggestions of this user id}
        {--from= : Only rebuild suggestions on or after this date (Y-m-d)}
        {--to= : Only rebuild suggestions on or before this date (Y-m-d)}';

    protected $description = 'Rebuild the activities and open suggestions of every user-day that has an open suggestion';

    public function handle(): int
    {
        $userDays = EntrySuggestion::query()
            ->whereDoesntHave('entry')
            ->when($this->option('user'), fn ($query, $user) => $query->where('user_id', $user))
            ->when($this->option('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($this->option('to'), fn ($query, $to) => $query->where('date', '<=', $to))
            ->get(['user_id', 'date'])
            ->map(fn (EntrySuggestion $suggestion) => ['userId' => (int) $suggestion->user_id, 'date' => (string) $suggestion->date])
            ->unique(fn (array $userDay) => $userDay['userId'].':'.$userDay['date'])
            ->values();

        $userDays->each(fn (array $userDay) => RebuildUserDay::dispatchSync($userDay['userId'], $userDay['date']));

        $this->info(sprintf('Rebuilt %d user-days.', $userDays->count()));

        return self::SUCCESS;
    }
}
