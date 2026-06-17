<?php

namespace App\Listeners;

use App\Events\MinutesSpentSetOnEntry;
use App\Mail\BudgetUsageWarning as WarningMail;
use App\Models\Budget;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Mail;

class BudgetUsageWarning implements ShouldQueue
{
    public function __construct(
        private LogManager $logManager,
    ) {}

    public function handle(MinutesSpentSetOnEntry $event): void
    {
        $entry = $event->getEntry();
        if (is_null($entry->budget) || is_null($entry->minutes_spent)) {
            return;
        }

        $budget = $entry->budget;
        $period = $budget->getPeriodAt($entry->started_at);
        if (is_null($period)) {
            throw new Exception('No period for budget');
        }
        $remainingMinutes = $period->getRemainingMinutes(true);
        $balanceBefore = $remainingMinutes + $entry->minutes_spent;

        $spendMinutes = $period->getSpentMinutes();
        $spendMinutesBefore = $period->getSpentMinutes() - $entry->minutes_spent;

        $activeBudget = $budget->activeVersion($entry->started_at);
        if ($activeBudget->initial_minutes == 0) {
            return;
        }

        $percentageUsedNow = $spendMinutes / $activeBudget->initial_minutes * 100;
        $percentageUsedPreviously = $spendMinutesBefore / $activeBudget->initial_minutes * 100;

        if ($budget->renewal_frequency !== null) {
            $notifyThresholds = [100];
        } else {
            $notifyThresholds = [70, 90, 100];
        }

        if ($highestThreshold = $this->getHighestThresholdReached((float) $percentageUsedNow, (float) $percentageUsedPreviously, $notifyThresholds)) {
            $this->logManager->debug(
                'BudgetUsageWarning',
                [
                    'budgetId' => $budget->id,
                    'balanceBefore' => $balanceBefore,
                    'minutesSpent' => $entry->minutes_spent,
                    'remainingMinutes' => $remainingMinutes,
                    'entryId' => $entry->id,
                ]
            );

            $this->notifyOnUsedBudget($budget, (string) $highestThreshold);
        } else {
            // lowest threshold or different threshold not reached, no notification to send
            return;
        }
    }

    /**
     * Checks if a new threshold is reached and if so returns it.
     *
     * @param  array<int,float>  $thresholds
     * @return float|null Returns the highest threshold that has been surpassed or null if non
     */
    private function getHighestThresholdReached(float $newValue, float $oldValue, array $thresholds): ?float
    {
        if (count($thresholds) === 0 || $newValue < min($thresholds) || $newValue < $oldValue) {
            return null;
        }

        $thresholds = collect($thresholds)->sortDesc(SORT_NUMERIC);
        foreach ($thresholds->all() as $threshold) {
            if ($newValue >= $threshold && $oldValue < $threshold) {
                return $threshold;
            }
        }

        return null;
    }

    private function notifyOnUsedBudget(Budget $budget, string $amountUsed): void
    {
        $mailTo[] = $budget->customer->accountManager->email ?? config('timatic.account_management_mail_address');

        if ($budget->supervisor) {
            $mailTo[] = $budget->supervisor->email;
        }

        assert(! is_null($budget->customer));

        Mail::to($mailTo)
            ->send(new WarningMail($budget, $budget->customer, $amountUsed));
    }
}
