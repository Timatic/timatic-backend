<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Services\SuggestionBundler;
use Illuminate\Console\Command;

class RebundleSuggestionsCommand extends Command
{
    protected $signature = 'timatic:rebundle-suggestions
        {--user= : Only rebundle suggestions of this user id}
        {--from= : Only rebundle suggestions on or after this date (Y-m-d)}
        {--to= : Only rebundle suggestions on or before this date (Y-m-d)}';

    protected $description = 'Delete open (not accepted, not rejected) suggestions and rebundle their activities chronologically';

    public function handle(SuggestionBundler $bundler): int
    {
        $suggestionIds = EntrySuggestion::query()
            ->whereDoesntHave('entry')
            ->when($this->option('user'), fn ($query, $user) => $query->where('user_id', $user))
            ->when($this->option('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($this->option('to'), fn ($query, $to) => $query->where('date', '<=', $to))
            ->pluck('id');

        $activityIds = Activity::query()
            ->whereIn('entry_suggestion_id', $suggestionIds)
            ->pluck('id');

        // detach first: activities.entry_suggestion_id cascades on suggestion delete
        Activity::query()->whereIn('id', $activityIds)->update(['entry_suggestion_id' => null]);
        EntrySuggestion::query()->whereKey($suggestionIds)->forceDelete();

        Activity::query()
            ->whereIn('id', $activityIds)
            ->orderBy('started_at')
            ->get()
            ->each(fn (Activity $activity) => $bundler->bundle($activity));

        $this->info(sprintf(
            'Rebundled %d activities from %d suggestions into %d suggestions.',
            $activityIds->count(),
            $suggestionIds->count(),
            EntrySuggestion::query()->whereIn('id', Activity::query()->whereIn('id', $activityIds)->pluck('entry_suggestion_id'))->count(),
        ));

        return self::SUCCESS;
    }
}
