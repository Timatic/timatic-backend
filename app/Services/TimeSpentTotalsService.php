<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\BudgetTimeSpentTotal;
use App\Models\Budget;
use App\Models\Entry;
use App\Models\TimeSpentTotal;
use App\Models\UserCustomerHoursRecord;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TimeSpentTotalsService
{
    public function getTimeSpentTotalsPerPeriod(
        string $unit,
        Carbon|CarbonImmutable $startedAtStart,
        Carbon|CarbonImmutable $startedAtEnd,
        ?int $teamId = null,
        ?int $userId = null,
    ): Collection {
        $periods = new Collection;

        $startOfUnitPeriod = $startedAtStart->startOf($unit);

        do {
            $records = UserCustomerHoursRecord::query()
                ->where('started_at', '>=', $startOfUnitPeriod)
                ->where('started_at', '<=', $startOfUnitPeriod->endOf($unit))
                ->when(! is_null($teamId), function ($query) use ($teamId) {
                    $query->where('user_team_id', $teamId);
                })
                ->when(! is_null($userId), function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->get();

            $periods->add(new TimeSpentTotal(
                start: $startOfUnitPeriod,
                end: $startOfUnitPeriod->endOf($unit),
                internalMinutes: (int) $records->sum('internal_minutes'),
                billableMinutes: (int) $records->sum('paid_per_hour_minutes')
                + (int) $records->sum('budget_minutes'),
                periodUnit: $unit,
                periodValue: $startOfUnitPeriod->$unit,
            ));

            $startOfUnitPeriod = $startOfUnitPeriod->add($unit, 1);
        } while ($startOfUnitPeriod->lessThan($startedAtEnd));

        return $periods;
    }

    /**
     * @return Collection<int, BudgetTimeSpentTotal>
     */
    public function getTimeSpentTotalsPerBudget(
        string $unit,
        int $budgetId
    ): Collection {
        $periods = new Collection;

        $budget = Budget::findOrFail($budgetId);

        $startedAtStart = $budget->started_at;
        $startedAtEnd = $budget->ended_at?->min(Carbon::now());
        assert(! is_null($startedAtStart));
        $startOfUnitPeriod = $startedAtStart;
        $remainingMinutes = $budget->activeVersion()->initial_minutes;

        do {
            $entries = Entry::where('started_at', '>=', $startOfUnitPeriod)
                ->where('started_at', '<=', $startOfUnitPeriod->clone()->endOf($unit))
                ->where('budget_id', '=', $budget->id)
                ->get();

            $remainingMinutes -= $entries->sum('minutes_spent');

            $periods->add(new BudgetTimeSpentTotal(
                budgetId: $budget->id,
                start: $startOfUnitPeriod->clone(),
                end: $startOfUnitPeriod->clone()->endOf($unit),
                remainingMinutes: $remainingMinutes,
                periodUnit: $unit,
                periodValue: $startOfUnitPeriod->$unit,
            ));

            $startOfUnitPeriod = $startOfUnitPeriod->startOf($unit)->add($unit, 1);
        } while ($startOfUnitPeriod->lessThan($startedAtEnd));

        return $periods;
    }
}
