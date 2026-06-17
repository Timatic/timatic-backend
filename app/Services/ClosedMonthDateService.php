<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

class ClosedMonthDateService
{
    public function __construct(private Authenticatable $user) {}

    public function get(): CarbonImmutable
    {
        $today = CarbonImmutable::today(config('timatic.preferred_timezone'));

        $closingDay = config('timatic.month-end_closing_day_of_month');

        if ($this->user->can('entries.update_from_previous_month')) {
            $closingDay = config('timatic.extended_closing_day_of_month');
        }

        if ($today->day < $closingDay) {
            return CarbonImmutable::today(config('timatic.preferred_timezone'))->startOfMonth()->subMonth();
        } else {
            return CarbonImmutable::today(config('timatic.preferred_timezone'))->startOfMonth();
        }
    }
}
