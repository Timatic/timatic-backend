<?php

namespace App\Console\Commands;

use App\Mail\BudgetMonthlyBalance;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Console\Command\Command as BaseCommand;

class SendBudgetBalanceOverview extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'budget:balance-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send monthly balance overview to account managers and supervisors';

    private int $commandOutput = BaseCommand::SUCCESS;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Sending to account managers...');
        $groupedByAccountManager = $this->getBudgetsGroupedByAccountManager();
        $this->sendMails($groupedByAccountManager, 'account manager');

        $this->info('Sending to supervisors...');
        $groupedBySupervisor = $this->getBudgetsGroupedBySuperVisor();
        $this->sendMails($groupedBySupervisor, 'supervisor');

        $this->info('All overviews send!');

        return $this->commandOutput;
    }

    private function getBudgetsGroupedByAccountManager(): Collection
    {
        return Budget::query()
            ->isArchived(false)
            ->with('customer')
            ->whereHas('customer', function (Builder $query) {
                $query->whereNotNull('account_manager_user_id');
            })
            ->get()
            ->groupBy('customer.account_manager_user_id');
    }

    // @phpstan-ignore-next-line For some reason phpstan thinks this method is unused (while it's used in handle())
    private function getBudgetsGroupedBySupervisor(): Collection
    {
        return Budget::query()
            ->isArchived(false)
            ->whereNotNull('supervisor_user_id')
            ->get()
            ->groupBy('supervisor_user_id');
    }

    private function sendMails(Collection $groupedBudgets, string $role): void
    {
        foreach ($groupedBudgets as $userId => $budgets) {
            if (empty($userId)) {
                $this->commandOutput = BaseCommand::FAILURE;

                continue;
            }

            try {
                $user = User::query()->where('id', $userId)->sole();
                Mail::to($user->email)->send(new BudgetMonthlyBalance($user, $role, $budgets));
            } catch (\Throwable $th) {
                $this->commandOutput = BaseCommand::FAILURE;
                $this->error(sprintf('No user found for %s: %s: %s', $userId, $th::class, $th->getMessage()));
            }
        }
    }
}
