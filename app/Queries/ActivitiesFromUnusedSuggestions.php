<?php

namespace App\Queries;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesFromUnusedSuggestions
{
    /**
     * @return Builder<Activity>
     */
    public static function query(): Builder
    {
        return Activity::query()
            ->whereRaw('entry_suggestion_id NOT IN (SELECT id FROM entry_suggestions WHERE deleted_at IS NOT NULL)');
    }
}
