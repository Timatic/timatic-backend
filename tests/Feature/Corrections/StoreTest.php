<?php

use App\Models\Entry;
use App\Services\MinutesSpentCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\LoginUser;

use function Pest\Laravel\postJson;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    Event::fake();
});

it('stores a correction and makes a correction entry', function () {
    $this->loginUser(permissions: ['user', 'corrections.create']);

    /** @var Entry $entry */
    $entry = Entry::factory()->make();
    calculateMinutesSpent($entry);
    $entry->save();

    $response = postCorrection($entry)->assertCreated()->json();

    assertCorrectedEntry($response['data']['relationships']['correctionEntry']['data']['id'], $entry);
});

it('stores a correction and corrects a negative entry to positive', function () {
    $this->loginUser(permissions: ['user', 'corrections.create']);

    /** @var Entry $entry */
    $entry = Entry::factory()->correction()->make();
    calculateMinutesSpent($entry);
    $entry->save();

    $response = postCorrection($entry)->assertCreated()->json();

    assertCorrectedEntry($response['data']['relationships']['correctionEntry']['data']['id'], $entry);
});

function calculateMinutesSpent(Entry $entry): Entry
{
    /** @var MinutesSpentCalculator $calculator */
    $calculator = app(MinutesSpentCalculator::class);

    $entry->minutes_spent = $calculator->calculate($entry);

    return $entry;
}

function postCorrection(Entry $entry): TestResponse
{
    return postJson('corrections?include=correctionEntry', [
        'data' => [
            'type' => 'corrections',
            'attributes' => [
                'correctedEntryId' => $entry->id,
            ],
        ],
    ]);
}

function assertCorrectedEntry($correctionEntryId, Entry $entry): void
{
    $correctionEntry = Entry::query()->find($correctionEntryId);

    expect($correctionEntry)->toBeInstanceOf(Entry::class);

    expect($correctionEntry->minutes_spent)->toEqual($entry->minutes_spent * -1);
}
