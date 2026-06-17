<?php

namespace Timatic\Rework\Commands;

use App\Models\Budget;
use App\Models\BudgetType;
use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Timatic\Rework\Connector;
use Timatic\Rework\DataTransferObjects\ReworkLeaveRequest;
use Timatic\Rework\DataTransferObjects\ReworkLeaveSlot;
use Timatic\Rework\Requests\GetLeaveRequestsRequest;

class SyncLeaveFromReworkCommand extends Command
{
    protected $signature = 'rework:sync-leave';

    protected $description = 'Sync today\'s approved leave from Rework as entries in Timatic';

    public function handle(Connector $connector): int
    {
        $integration = Integration::where('type', 'rework')->firstOrFail();
        $config = $integration->config ?? [];

        if (! ($config['sync_leave_enabled'] ?? true)) {
            $this->info('Leave sync is disabled.');

            return self::SUCCESS;
        }

        $leaveBudget = Budget::whereHas('budgetType', fn ($q) => $q->where('id', BudgetType::LEAVE))->first();

        if ($leaveBudget === null) {
            $this->error('No leave budget found in Timatic.');

            return self::FAILURE;
        }

        $this->info('Fetching approved leave requests from Rework...');

        /** @var Collection<int, ReworkLeaveRequest> $leaveRequests */
        $leaveRequests = $connector->paginate(new GetLeaveRequestsRequest)
            ->collect()
            ->values();

        $this->info("Fetched {$leaveRequests->count()} approved leave request(s).");

        $today = CarbonImmutable::today('Europe/Amsterdam');
        $synced = 0;

        foreach ($leaveRequests as $leaveRequest) {
            $todaySlots = $leaveRequest->slotsForToday();

            if ($todaySlots->isEmpty()) {
                continue;
            }

            $user = User::query()->where('email', $leaveRequest->userEmail)->first();

            if ($user === null) {
                $this->warn("No Timatic user found for {$leaveRequest->userEmail} — skipping.");

                continue;
            }

            $alreadySynced = Entry::where('user_id', $user->id)
                ->where('budget_id', $leaveBudget->id)
                ->whereDate('started_at', $today->toDateString())
                ->exists();

            if ($alreadySynced) {
                $this->info("Leave for {$leaveRequest->userEmail} already synced today — skipping.");

                continue;
            }

            foreach ($todaySlots as $slot) {
                $this->createEntryForSlot($user, $leaveBudget, $today, $slot);
                $synced++;
            }

            $this->info("Synced leave for {$leaveRequest->userEmail}.");
        }

        $this->info("Done. Synced {$synced} leave entry/entries.");

        return self::SUCCESS;
    }

    private function createEntryForSlot(User $user, Budget $leaveBudget, CarbonImmutable $today, ReworkLeaveSlot $slot): void
    {
        $durationInMinutes = $slot->minutesForDay();
        $startedAt = $today->setHour(9);
        $endedAt = $startedAt->addMinutes($durationInMinutes);

        Entry::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_full_name' => $user->full_name,
            'budget_id' => $leaveBudget->id,
            'customer_id' => $leaveBudget->customer_id,
            'entry_type' => 'regular',
            'description' => 'Verlof (Rework)',
            'is_internal' => false,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }
}
