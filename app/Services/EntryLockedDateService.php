<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;

class EntryLockedDateService
{
    public function __construct(private Authenticatable $user, private ClosedMonthDateService $closedMonthDateService) {}

    public function get(): CarbonImmutable
    {
        $today = CarbonImmutable::today(config('timatic.preferred_timezone'));

        if ($this->user->can('entries.update_from_previous_month')) {
            // finance and backoffice users can edit entries in the previous month
            $canMutateEntriesUntil = $today->startOfMonth()->subMonth();
        } else {
            // default users can edit entries up to max 10 days in the past, to prevent inaccurate entries
            $canMutateEntriesUntil = $today->subDays(config('timatic.entries_locked_after_days'));
        }

        $financeClosedMonthDate = $this->closedMonthDateService->get();

        if ($canMutateEntriesUntil->isBefore($financeClosedMonthDate)) {
            $canMutateEntriesUntil = $financeClosedMonthDate;
        }

        return $canMutateEntriesUntil->subSecond();
    }
}
