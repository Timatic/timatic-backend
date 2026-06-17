<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach()->loginUser(permissions: ['user']);

it('serves time spent totals', function () {
    Event::fake();

    Entry::factory()->count(3)->create([
        'started_at' => now()->subWeek()->setTime(10, 0),
        'ended_at' => now()->subWeek()->setTime(10, 30),
    ]);

    Entry::factory()->count(3)->create([
        'started_at' => now()->sub('week', 2)->setTime(11, 0),
        'ended_at' => now()->sub('week', 2)->setTime(13, 0),
        'is_internal' => true,
    ]);

    $params = 'periodUnit=week';
    $params .= '&filter[startedAt][lte]='.urlencode(now()->startOf('week'));
    $params .= '&filter[startedAt][gte]='.urlencode(now()->startOf('week')->subWeeks(2));

    $uri = 'time-spent-totals?'.$params;

    $this->get($uri)->assertSuccessful()
        ->assertJson([
            'meta' => [
                'totalInternalMinutes' => 360,
                'totalBillableMinutes' => 90,
            ],
        ])
        ->assertJsonStructure([
            'data' => [[
                'id',
                'type',
                'attributes' => [
                    'start',
                    'end',
                    'internalMinutes',
                    'billableMinutes',
                ],
            ]],
        ]);
});

it('ignores deleted entries', function () {
    Event::fake();

    /** @var Entry $deletedEntry */
    $deletedEntry = Entry::factory()->create([
        'started_at' => now()->subWeek()->setTime(10, 0),
        'ended_at' => now()->subWeek()->setTime(10, 30),
    ]);

    $deletedEntry->delete();

    $params = 'periodUnit=week';
    $params .= '&filter[startedAt][lte]='.urlencode(now()->startOf('week'));
    $params .= '&filter[startedAt][gte]='.urlencode(now()->startOf('week')->subWeeks(2));

    $uri = 'time-spent-totals?'.$params;

    $this->get($uri)->assertSuccessful()
        ->assertJson([
            'meta' => [
                'totalInternalMinutes' => 0,
                'totalBillableMinutes' => 0,
            ],
        ]);
});
