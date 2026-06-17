<?php

use App\Models\Entry;
use App\Services\TimeSpentTotalsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('shows total time spent in weeks', function () {
    Event::fake();

    $service = $this->app->make(TimeSpentTotalsService::class);

    $startedAtStart = CarbonImmutable::now()->sub('week', 5)->startOf('week');
    $startedAtEnd = CarbonImmutable::now()->sub('week', 3)->endOf('week');

    $firstWeek = $startedAtStart->startOf('week');

    Entry::factory()->count(2)->create([
        'started_at' => $firstWeek->addDay()->setTime(10, 0),
        'ended_at' => $firstWeek->addDay()->setTime(11, 0),
    ]);

    $secondWeek = $startedAtStart->addWeek()->startOf('week');
    Entry::factory()->count(3)->create([
        'started_at' => $secondWeek->addDay()->setTime(10, 0),
        'ended_at' => $secondWeek->addDay()->setTime(10, 45),
    ]);

    $thirdWeek = $startedAtStart->add('week', 2);
    Entry::factory()->count(4)->create([
        'budget_id' => null,
        'is_internal' => true,
        'started_at' => $thirdWeek->addDays(3)->setTime(10, 0),
        'ended_at' => $thirdWeek->addDays(3)->setTime(10, 23),
    ]);

    $totals = $service->getTimeSpentTotalsPerPeriod(
        unit: 'week',
        startedAtStart: $startedAtStart,
        startedAtEnd: $startedAtEnd,
    );

    expect($totals[0]->billableMinutes)->toEqual(2 * 60);
    expect($totals[1]->billableMinutes)->toEqual(3 * 45);
    expect($totals[2]->internalMinutes)->toEqual(4 * 23);
});
