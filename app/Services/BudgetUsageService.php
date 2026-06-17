<?php

namespace App\Services;

use App\DataTransferObjects\BudgetMutation;
use App\Models\Budget;
use App\Models\Period;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BudgetUsageService
{
    /**
     * @return Collection|BudgetMutation[]
     */
    public function get(Carbon $month): Collection
    {
        $startOfMonth = $month->clone()->firstOfMonth();
        $endOfMonth = $month->clone()->endOfMonth();

        $budgets = Budget::query()
            ->where('ended_at', '>', $startOfMonth)
            ->where('started_at', '<', $endOfMonth)
            ->where(function (Builder $query) use ($startOfMonth) {
                $query->whereNull('archived_at')
                    ->orWhere(function (Builder $query) use ($startOfMonth) {
                        $query->Where('archived_at', '>', $startOfMonth)
                            ->whereRaw('archived_at > started_at');
                    });
            })
            ->orderBy('id')
            ->get();

        $budgetMutations = new Collection;

        foreach ($budgets as $budget) {
            /** @var Budget $budget */
            $budgetMutation = new BudgetMutation($budget);

            $startPeriod = $budget->getPeriodAt($startOfMonth);
            if (! $startPeriod) {
                $startPeriod = $budget->getPeriodAt($budget->started_at);
            }

            assert(! is_null($startPeriod));

            $budgetMutation->startBalance = $startPeriod->travelTo($startOfMonth)->getRemainingCredit();

            $this->parsePeriod($budgetMutation, $budget, $startPeriod, $startOfMonth, $endOfMonth);

            if (! $budget->renewal_frequency) {
                $budgetMutations->push($budgetMutation);

                continue;
            }

            // check if another period might have taken place in the same month.
            $endPeriod = $budget->getPeriodAt($endOfMonth);
            if (! $endPeriod || $startPeriod->getId() == $endPeriod->getId()) {
                $budgetMutations->push($budgetMutation);

                continue;
            }

            $budgetMutation->renewedCredit = BigDecimal::of($budget->getTotalPrice($startOfMonth))->toScale(2);

            $this->parsePeriod($budgetMutation, $budget, $endPeriod, $startOfMonth, $endOfMonth);

            $budgetMutations->push($budgetMutation);
        }

        return $budgetMutations;
    }

    private function parsePeriod(BudgetMutation $budgetMutation, Budget $budget, Period $period, Carbon $startOfMonth, Carbon $endOfMonth): void
    {
        $periodAtStartOfMonth = $period->travelTo($startOfMonth);
        $periodAtEndOfMonth = $period->travelTo($endOfMonth);

        $startBalance = $periodAtStartOfMonth->getRemainingCredit(true);
        $endBalance = $periodAtEndOfMonth->getRemainingCredit(true);
        $usedAmount = $startBalance->minus($endBalance);

        if ($startBalance->isGreaterThanOrEqualTo(0) && $endBalance->isGreaterThanOrEqualTo(0)) {
            $budgetMutation->usedCredit = $budgetMutation->usedCredit->plus($usedAmount);
        } elseif ($startBalance->isLessThanOrEqualTo(0) && $endBalance->isLessThanOrEqualTo(0)) {
            $budgetMutation->usedOutOfBudget = $budgetMutation->usedOutOfBudget->plus($usedAmount);
        } else {
            // a part is in budget and a part is out of budget
            /** @var BigDecimal $balanceTop */
            $balanceTop = max($startBalance, $endBalance);
            /** @var BigDecimal $balanceLow */
            $balanceLow = min($startBalance, $endBalance);

            $budgetMutation->usedCredit = $budgetMutation->usedCredit->plus($balanceTop);
            $budgetMutation->usedOutOfBudget = $budgetMutation->usedOutOfBudget->plus($balanceLow->abs());

            if ($usedAmount->isLessThan(0)) {
                $budgetMutation->usedCredit = $budgetMutation->usedCredit->multipliedBy(-1);
                $budgetMutation->usedOutOfBudget = $budgetMutation->usedOutOfBudget->multipliedBy(-1);
            }
        }

        // if the period->endDate is within this month, we might have expired credit.
        if ($periodAtEndOfMonth->getEndDate()->lessThanOrEqualTo($endOfMonth)
            || $budget->archived_at?->lessThanOrEqualTo($endOfMonth)
            || $budget->ended_at?->lessThanOrEqualTo($endOfMonth)
        ) {
            $budgetMutation->expiredCredit = $periodAtEndOfMonth->getRemainingCredit();
            $budgetMutation->endBalance = BigDecimal::of(0);
        } else {
            // the period ends later, so register the endBalance at endOfMonth
            $budgetMutation->endBalance = $periodAtEndOfMonth->getRemainingCredit();
        }
    }
}
