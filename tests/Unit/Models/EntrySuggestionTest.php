<?php

use App\Exceptions\ConflictingSuggestionFieldException;
use App\Models\Activity;
use App\Models\EntrySuggestion;

test('an activity on another ticket can not be added to a suggestion', function () {
    $suggestion = new EntrySuggestion;
    $suggestion->ticket_number = 'TIC-1';
    $activity = new Activity;
    $activity->ticket_number = 'TIC-2';

    $suggestion->addActivity($activity);
})->throws(ConflictingSuggestionFieldException::class);
