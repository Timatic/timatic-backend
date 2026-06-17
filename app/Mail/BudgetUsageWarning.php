<?php

namespace App\Mail;

use App\Models\Budget;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetUsageWarning extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected Budget $budget,
        protected Customer $customer,
        protected string $percentageUsed
    ) {
        //
    }

    /**
     * Build the message.
     */
    public function build(Repository $config): static
    {
        $subject = $this->percentageUsed >= 100 ? 'Budget warning' : 'Budget update';

        return $this->markdown('mail.budgets.usage-warning', [
            'budgetTitle' => $this->budget->getTitle(),
            'budgetUrl' => rtrim($config->get('app.frontend_url'), '/').'/budgets/'.$this->budget->id.'/',
            'customerName' => $this->customer->name,
            'percentageUsed' => $this->percentageUsed,
        ])
            ->subject("{$subject}: {$this->percentageUsed}% of budget used for {$this->customer->name}");
    }
}
