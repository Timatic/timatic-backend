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

test('overlapping activities are counted once in the minutes spent', function () {
    $suggestion = new EntrySuggestion;
    $suggestion->addActivity(new Activity([
        'started_at' => '2026-11-02 09:00:00',
        'ended_at' => '2026-11-02 09:30:00',
    ]));
    $suggestion->addActivity(new Activity([
        'started_at' => '2026-11-02 09:20:00',
        'ended_at' => '2026-11-02 09:45:00',
    ]));

    expect($suggestion->minutesSpent())->toBe(45);
});
