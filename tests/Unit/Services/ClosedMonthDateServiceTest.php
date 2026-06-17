<?php

use App\Services\ClosedMonthDateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

it('returns closed month date correctly when today is before month end closing day of month', function () {
    $this->loginUser();

    Config::set('timatic.month-end_closing_day_of_month', 5);

    $today = CarbonImmutable::parse('2023-04-04');

    $this->travelTo($today);

    expect(app()->make(ClosedMonthDateService::class)->get())->toEqual(new CarbonImmutable('2023-03-01', config('timatic.preferred_timezone')));
});

it('returns closed month date correctly when today is after month end closing day of month', function () {
    $this->loginUser();
    Config::set('timatic.month-end_closing_day_of_month', 5);

    $today = CarbonImmutable::parse('2023-04-05');

    $this->travelTo($today);

    expect(app()->make(ClosedMonthDateService::class)->get())->toEqual(new CarbonImmutable('2023-04-01', config('timatic.preferred_timezone')));
});
