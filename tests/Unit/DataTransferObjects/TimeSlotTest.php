<?php

use App\DataTransferObjects\TimeSlot;
use Carbon\Carbon;

test('periods that touch but do not overlap are not overlapping', function () {
    $first = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 10:00'));
    $second = new TimeSlot(Carbon::parse('2026-07-16 10:00'), Carbon::parse('2026-07-16 11:00'));

    expect($first->overlaps($second))->toBeFalse()
        ->and($second->overlaps($first))->toBeFalse();
});

test('a period overlapping another partially is overlapping', function () {
    $first = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 10:30'));
    $second = new TimeSlot(Carbon::parse('2026-07-16 10:00'), Carbon::parse('2026-07-16 11:00'));

    expect($first->overlaps($second))->toBeTrue()
        ->and($second->overlaps($first))->toBeTrue();
});

test('a period covers another when it fully contains it', function () {
    $outer = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 12:00'));
    $inner = new TimeSlot(Carbon::parse('2026-07-16 10:00'), Carbon::parse('2026-07-16 11:00'));

    expect($outer->covers($inner))->toBeTrue()
        ->and($inner->covers($outer))->toBeFalse();
});

test('subtracting a blocker in the middle splits the period in two segments', function () {
    $period = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 12:00'));
    $blocker = new TimeSlot(Carbon::parse('2026-07-16 10:00'), Carbon::parse('2026-07-16 11:00'));

    $segments = $period->subtract(collect([$blocker]));

    expect($segments)->toHaveCount(2)
        ->and($segments[0]->startedAt)->toEqual(Carbon::parse('2026-07-16 09:00'))
        ->and($segments[0]->endedAt)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($segments[1]->startedAt)->toEqual(Carbon::parse('2026-07-16 11:00'))
        ->and($segments[1]->endedAt)->toEqual(Carbon::parse('2026-07-16 12:00'));
});

test('subtracting a covering blocker leaves no segments', function () {
    $period = new TimeSlot(Carbon::parse('2026-07-16 10:00'), Carbon::parse('2026-07-16 11:00'));
    $blocker = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 12:00'));

    expect($period->subtract(collect([$blocker])))->toBeEmpty();
});

test('subtracting an overlapping blocker trims the period', function () {
    $period = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 11:00'));
    $blocker = new TimeSlot(Carbon::parse('2026-07-16 08:00'), Carbon::parse('2026-07-16 10:00'));

    $segments = $period->subtract(collect([$blocker]));

    expect($segments)->toHaveCount(1)
        ->and($segments[0]->startedAt)->toEqual(Carbon::parse('2026-07-16 10:00'))
        ->and($segments[0]->endedAt)->toEqual(Carbon::parse('2026-07-16 11:00'));
});

test('subtracting nothing returns the period itself', function () {
    $period = new TimeSlot(Carbon::parse('2026-07-16 09:00'), Carbon::parse('2026-07-16 11:00'));

    $segments = $period->subtract(collect());

    expect($segments)->toHaveCount(1)
        ->and($segments[0])->toBe($period);
});
