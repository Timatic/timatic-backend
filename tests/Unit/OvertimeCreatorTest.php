<?php

use App\Models\Overtime;
use App\Services\OvertimeCreator;
use Database\Seeders\OvertimeRuleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    (new OvertimeRuleSeeder)->run();
});

it('calculates percentage distribution', function () {
    $overtime = calculatePercentageDistribution(Carbon::parse('2020-11-09 17:00:00'), Carbon::parse('2020-11-10 03:00:00'));

    $eveningMinutes = (int) Carbon::parse('2020-11-10')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->started_at, true);

    $nightMinutes = (int) Carbon::parse('2020-11-10')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->ended_at, true);

    expect($overtime->percentages->evening->minutes)->toEqual($eveningMinutes)
        ->and($overtime->percentages->night->minutes)->toEqual($nightMinutes);
});

it('calculates weekend start percentage distribution', function () {
    $overtime = calculatePercentageDistribution(Carbon::parse('2020-11-13 20:00:00'), Carbon::parse('2020-11-14 02:00:00'));

    $eveningMinutes = (int) Carbon::parse('2020-11-14')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->started_at, true);

    $weekendMinutes = (int) Carbon::parse('2020-11-14')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->ended_at, true);

    expect($overtime->percentages->evening->minutes)->toEqual($eveningMinutes)
        ->and($overtime->percentages->weekend->minutes)->toEqual($weekendMinutes);
});

it('calculated weekend end percentage distribution', function () {
    $overtime = calculatePercentageDistribution(Carbon::parse('2020-11-15 19:00:00'), Carbon::parse('2020-11-16 05:00:00'));

    $weekendMinutes = (int) Carbon::parse('2020-11-16')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->started_at, true);

    $nightMinutes = (int) Carbon::parse('2020-11-16')
        ->timezone('Europe/Amsterdam')
        ->setTimeFromTimeString('00:00')
        ->timezone('UTC')
        ->diffInMinutes($overtime->ended_at, true);

    expect($overtime->percentages->weekend->minutes)->toEqual($weekendMinutes)
        ->and($overtime->percentages->night->minutes)->toEqual($nightMinutes);
});

function calculatePercentageDistribution(Carbon $startedAt, Carbon $endedAt): Overtime
{
    /** @var OvertimeCreator $overtimeCreator */
    $overtimeCreator = resolve(OvertimeCreator::class);

    $overtime = new Overtime;
    $overtime->started_at = Carbon::parse($startedAt);
    $overtime->ended_at = Carbon::parse($endedAt);
    $overtime->percentages = $overtimeCreator->calculatePercentageDistribution($overtime);

    return $overtime;
}
