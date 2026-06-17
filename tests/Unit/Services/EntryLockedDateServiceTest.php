<?php

use App\Services\EntryLockedDateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\LoginUser;

uses(LoginUser::class);

uses(RefreshDatabase::class);

uses(WithFaker::class);

beforeEach(function () {
    Event::fake();
});

it('returns entry editable date for users that can update entries from previous month', function () {
    $user = $this->loginUser(permissions: ['user', 'entries.update_from_previous_month']);

    $today = CarbonImmutable::parse('2023-04-04', config('timatic.preferred_timezone'));

    $this->travelTo($today);

    $canMutateEntriesUntil = app()->make(EntryLockedDateService::class)->get();

    expect($canMutateEntriesUntil)->toEqual($today->startOfMonth()->subMonth()->subSecond());
});

it('returns entry editable date for default users', function () {
    $user = $this->loginUser(permissions: ['user']);

    $today = CarbonImmutable::parse('2023-04-04', config('timatic.preferred_timezone'));

    $this->travelTo($today);

    $canMutateEntriesUntil = app()->make(EntryLockedDateService::class)->get();

    expect($canMutateEntriesUntil)->toEqual($today->subDays(config('timatic.entries_locked_after_days'))->subSecond());
});
