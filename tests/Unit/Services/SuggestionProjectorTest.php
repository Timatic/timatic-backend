<?php

use App\Models\Activity;
use App\Models\EntrySuggestion;
use App\Services\SuggestionProjector;
use Carbon\Carbon;

test('activities sharing customer, budget, ticket and is_internal bundle into one suggestion', function () {
    $first = new Activity;
    $first->user_id = 1;
    $first->customer_id = 'customerX';
    $first->budget_id = 7;
    $first->ticket_number = 'TIC-1';
    $first->ticket_id = 'uuid-1';
    $first->ticket_type = 'incident';
    $first->is_internal = false;
    $first->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $first->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $second = new Activity;
    $second->user_id = 1;
    $second->customer_id = 'customerX';
    $second->budget_id = 7;
    $second->ticket_number = 'TIC-1';
    $second->ticket_id = 'uuid-1';
    $second->ticket_type = 'incident';
    $second->is_internal = false;
    $second->started_at = Carbon::parse('2026-07-16 14:00', 'Europe/Amsterdam');
    $second->ended_at = Carbon::parse('2026-07-16 15:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$first, $second]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->activities)->toHaveCount(2)
        ->and($suggestions[0]->ticket_number)->toBe('TIC-1')
        ->and($suggestions[0]->customer_id)->toBe('customerX')
        ->and($suggestions[0]->date)->toBe('2026-07-16');
});

test('activities on different budgets get separate suggestions', function () {
    $first = new Activity;
    $first->user_id = 1;
    $first->customer_id = 'customerX';
    $first->budget_id = 7;
    $first->ticket_number = 'TIC-1';
    $first->is_internal = false;
    $first->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $first->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $second = new Activity;
    $second->user_id = 1;
    $second->customer_id = 'customerX';
    $second->budget_id = 8;
    $second->ticket_number = 'TIC-1';
    $second->is_internal = false;
    $second->started_at = Carbon::parse('2026-07-16 14:00', 'Europe/Amsterdam');
    $second->ended_at = Carbon::parse('2026-07-16 15:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$first, $second]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(2);
});

test('internal and external activities on the same ticket get separate suggestions', function () {
    $internal = new Activity;
    $internal->user_id = 1;
    $internal->customer_id = 'customerX';
    $internal->budget_id = 7;
    $internal->ticket_number = 'TIC-1';
    $internal->is_internal = true;
    $internal->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $internal->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $external = new Activity;
    $external->user_id = 1;
    $external->customer_id = 'customerX';
    $external->budget_id = 7;
    $external->ticket_number = 'TIC-1';
    $external->is_internal = false;
    $external->started_at = Carbon::parse('2026-07-16 14:00', 'Europe/Amsterdam');
    $external->ended_at = Carbon::parse('2026-07-16 15:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$internal, $external]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(2);
});

test('a null-ticket activity chains onto a ticketed one of the same customer on the same day', function () {
    $ticketless = new Activity;
    $ticketless->user_id = 1;
    $ticketless->customer_id = 'customerX';
    $ticketless->budget_id = 7;
    $ticketless->ticket_number = null;
    $ticketless->is_internal = false;
    $ticketless->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $ticketless->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $ticketed = new Activity;
    $ticketed->user_id = 1;
    $ticketed->customer_id = 'customerX';
    $ticketed->budget_id = 7;
    $ticketed->ticket_number = 'TIC-1';
    $ticketed->is_internal = false;
    $ticketed->started_at = Carbon::parse('2026-07-16 14:00', 'Europe/Amsterdam');
    $ticketed->ended_at = Carbon::parse('2026-07-16 15:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$ticketless, $ticketed]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->activities)->toHaveCount(2)
        ->and($suggestions[0]->ticket_number)->toBe('TIC-1');
});

test('no suggestion is projected when a dismissed suggestion matches the group key', function () {
    $activity = new Activity;
    $activity->user_id = 1;
    $activity->customer_id = 'customerX';
    $activity->budget_id = 7;
    $activity->ticket_number = 'TIC-1';
    $activity->is_internal = false;
    $activity->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $activity->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $dismissed = new EntrySuggestion;
    $dismissed->customer_id = 'customerX';
    $dismissed->budget_id = 7;
    $dismissed->ticket_number = 'TIC-1';
    $dismissed->is_internal = false;

    $suggestions = (new SuggestionProjector)->project(
        collect([$activity]),
        collect([$dismissed]),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toBeEmpty();
});

test('a budgetless activity chains onto a budgeted activity of the same ticket, and the suggestion keeps the budget', function () {
    $budgetless = new Activity;
    $budgetless->user_id = 1;
    $budgetless->customer_id = 'customerX';
    $budgetless->budget_id = null;
    $budgetless->ticket_number = 'TIC-1';
    $budgetless->is_internal = false;
    $budgetless->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $budgetless->ended_at = Carbon::parse('2026-07-16 09:10', 'Europe/Amsterdam');
    $budgeted = new Activity;
    $budgeted->user_id = 1;
    $budgeted->customer_id = 'customerX';
    $budgeted->budget_id = 7;
    $budgeted->ticket_number = 'TIC-1';
    $budgeted->is_internal = false;
    $budgeted->started_at = Carbon::parse('2026-07-16 14:00', 'Europe/Amsterdam');
    $budgeted->ended_at = Carbon::parse('2026-07-16 15:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$budgetless, $budgeted]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->activities)->toHaveCount(2)
        ->and($suggestions[0]->budget_id)->toBe(7);
});

test('activities with conflicting budgets do not chain even without ticket numbers', function () {
    $first = new Activity;
    $first->user_id = 1;
    $first->customer_id = 'customerX';
    $first->budget_id = 7;
    $first->ticket_number = null;
    $first->is_internal = false;
    $first->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $first->ended_at = Carbon::parse('2026-07-16 09:10', 'Europe/Amsterdam');
    $second = new Activity;
    $second->user_id = 1;
    $second->customer_id = 'customerX';
    $second->budget_id = 8;
    $second->ticket_number = null;
    $second->is_internal = false;
    $second->started_at = Carbon::parse('2026-07-16 09:12', 'Europe/Amsterdam');
    $second->ended_at = Carbon::parse('2026-07-16 09:20', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$first, $second]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(2);
});

test('a ticketless activity does not bridge two differently-ticketed activities', function () {
    $first = new Activity;
    $first->user_id = 1;
    $first->customer_id = 'customerX';
    $first->budget_id = 7;
    $first->ticket_number = 'TIC-1';
    $first->is_internal = false;
    $first->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $first->ended_at = Carbon::parse('2026-07-16 09:10', 'Europe/Amsterdam');
    $ticketless = new Activity;
    $ticketless->user_id = 1;
    $ticketless->customer_id = 'customerX';
    $ticketless->budget_id = 7;
    $ticketless->ticket_number = null;
    $ticketless->is_internal = false;
    $ticketless->started_at = Carbon::parse('2026-07-16 09:15', 'Europe/Amsterdam');
    $ticketless->ended_at = Carbon::parse('2026-07-16 09:20', 'Europe/Amsterdam');
    $third = new Activity;
    $third->user_id = 1;
    $third->customer_id = 'customerX';
    $third->budget_id = 7;
    $third->ticket_number = 'TIC-2';
    $third->is_internal = false;
    $third->started_at = Carbon::parse('2026-07-16 09:25', 'Europe/Amsterdam');
    $third->ended_at = Carbon::parse('2026-07-16 09:35', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$first, $ticketless, $third]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]->activities)->toHaveCount(2)
        ->and($suggestions[0]->ticket_number)->toBe('TIC-1')
        ->and($suggestions[1]->activities)->toHaveCount(1)
        ->and($suggestions[1]->ticket_number)->toBe('TIC-2');
});

test('customerless activities never bundle with each other', function () {
    $first = new Activity;
    $first->user_id = 1;
    $first->customer_id = null;
    $first->ticket_number = null;
    $first->is_internal = false;
    $first->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $first->ended_at = Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam');
    $second = new Activity;
    $second->user_id = 1;
    $second->customer_id = null;
    $second->ticket_number = null;
    $second->is_internal = false;
    $second->started_at = Carbon::parse('2026-07-16 09:31', 'Europe/Amsterdam');
    $second->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$first, $second]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(2);
});

test('a ticketless activity bundles with the nearest activity of the same customer', function () {
    $jira = new Activity;
    $jira->user_id = 1;
    $jira->customer_id = 'customerX';
    $jira->budget_id = 7;
    $jira->ticket_number = 'TIC-1';
    $jira->is_internal = false;
    $jira->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $jira->ended_at = Carbon::parse('2026-07-16 09:30', 'Europe/Amsterdam');
    $bitbucketNoTicket = new Activity;
    $bitbucketNoTicket->user_id = 1;
    $bitbucketNoTicket->customer_id = 'customerX';
    $bitbucketNoTicket->budget_id = 7;
    $bitbucketNoTicket->ticket_number = null;
    $bitbucketNoTicket->is_internal = false;
    $bitbucketNoTicket->started_at = Carbon::parse('2026-07-16 09:35', 'Europe/Amsterdam');
    $bitbucketNoTicket->ended_at = Carbon::parse('2026-07-16 09:45', 'Europe/Amsterdam');
    $bitbucketTicketed = new Activity;
    $bitbucketTicketed->user_id = 1;
    $bitbucketTicketed->customer_id = 'customerX';
    $bitbucketTicketed->budget_id = 7;
    $bitbucketTicketed->ticket_number = 'TIC-1';
    $bitbucketTicketed->is_internal = false;
    $bitbucketTicketed->started_at = Carbon::parse('2026-07-16 09:50', 'Europe/Amsterdam');
    $bitbucketTicketed->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');

    $suggestions = (new SuggestionProjector)->project(
        collect([$jira, $bitbucketNoTicket, $bitbucketTicketed]),
        collect(),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->activities)->toHaveCount(3)
        ->and($suggestions[0]->ticket_number)->toBe('TIC-1');
});

test('a dismissed suggestion with a different ticket does not suppress the group', function () {
    $activity = new Activity;
    $activity->user_id = 1;
    $activity->customer_id = 'customerX';
    $activity->budget_id = 7;
    $activity->ticket_number = 'TIC-1';
    $activity->is_internal = false;
    $activity->started_at = Carbon::parse('2026-07-16 09:00', 'Europe/Amsterdam');
    $activity->ended_at = Carbon::parse('2026-07-16 10:00', 'Europe/Amsterdam');
    $dismissed = new EntrySuggestion;
    $dismissed->customer_id = 'customerX';
    $dismissed->budget_id = 7;
    $dismissed->ticket_number = 'TIC-2';
    $dismissed->is_internal = false;

    $suggestions = (new SuggestionProjector)->project(
        collect([$activity]),
        collect([$dismissed]),
        Carbon::parse('2026-07-16', 'Europe/Amsterdam'),
    );

    expect($suggestions)->toHaveCount(1);
});
