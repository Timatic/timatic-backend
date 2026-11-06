<?php

namespace App\Mail;

use App\Models\Budget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class BudgetMonthlyBalance extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        private User $user,
        private string $role,
        private Collection $budgets
    ) {
        //
    }

    /**
     * Build the message.
     */
    public function build(): static
    {
        $budgets = $this->budgets
            ->groupBy('renewalFrequency')
            ->map(function (Collection $budgets) {
                return $budgets->sortBy('customer.name')
                    ->map(function (Budget $budget) {
                        $initialMinutes = $budget->activeVersion()->initial_minutes;
                        $period = $budget->getCurrentPeriodRelationData();
                        $usedPercentage = null;
                        $remainingMinutes = 0;

                        if ($period) {
                            $remainingMinutes = $period->getRemainingMinutes(true);
                            if ($initialMinutes > 0) {
                                $usedPercentage = 100 - floor($remainingMinutes / $initialMinutes * 100);
                            }
                        }

                        $usageWarning = ($budget->renewal_frequency == '' && $usedPercentage > 80) ||
                            ($budget->renewal_frequency !== '' && $usedPercentage > 100);

                        return [
                            'title' => $budget->activeVersion()->title,
                            'url' => sprintf('%s/budgets/%s', config('app.frontend_url'), $budget->id),
                            'customer' => $budget->customer?->name,
                            'remainingHours' => floor($remainingMinutes / 6) / 10,
                            'initialMinutes' => $initialMinutes,
                            'usedPercentage' => $usedPercentage,
                            'usageWarning' => $usageWarning,
                            'renewalFrequency' => $budget->renewal_frequency,
                            'expired' => $budget->ended_at?->isBefore(Carbon::today()),
                            'expiring' => $budget->ended_at?->isBefore(Carbon::today()->addMonth()),
                            'endedAt' => $budget->ended_at?->format('d-m-Y'),
                        ];
                    })->sortByDesc('usedPercentage');
            });

        return $this
            ->subject('Monthly Budget Balance ')
            ->markdown(
                'mail.budgets.monthly-balance',
                [
                    'user' => $this->user,
                    'role' => $this->role,
                    'budgetGroups' => $budgets,
                ]
            );
    }
}
