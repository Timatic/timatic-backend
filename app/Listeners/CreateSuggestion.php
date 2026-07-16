<?php

namespace App\Listeners;

use App\Events\ActivityCreated;
use App\Services\SuggestionBundler;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateSuggestion implements ShouldQueue
{
    public function __construct(private readonly SuggestionBundler $bundler) {}

    public function handle(ActivityCreated $activityCreated): void
    {
        $activity = $activityCreated->getActivity();

        if (config('timatic.feature.build_stacked_suggestions')) {
            $this->bundler->bundle($activity);
        } else {
            $this->bundler->createNewSuggestionFor($activity);
        }
    }
}
