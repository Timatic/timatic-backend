<?php

namespace Timatic\Nmbrs\Commands;

use App\Models\Budget;
use App\Models\BudgetType;
use App\Models\Entry;
use App\Models\Integration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Saloon\Http\Response;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployeePersonalInfo;
use Timatic\Nmbrs\DataTransferObjects\NmbrsLeaveRequest;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\GetLeaveRequestsRequest;
use Timatic\Nmbrs\Requests\GetPersonalInfoRequest;

class SyncLeaveFromNmbrsCommand extends Command
{
    protected $signature = 'nmbrs:sync-leave';

    protected $description = 'Sync today\'s approved leave from NMBRS as entries in Timatic';

    public function handle(OAuthService $oauthService): int
    {
        $integration = Integration::where('type', 'nmbrs')->firstOrFail();
        $integration = $oauthService->refreshIfExpired($integration);

        $config = $integration->config ?? [];

        if (! ($config['sync_leave_enabled'] ?? true)) {
            $this->info('Leave sync is disabled.');

            return self::SUCCESS;
        }

        $companyId = $config['company_id'] ?? null;

        if (empty($companyId)) {
            $this->error('No company_id configured. Reconnect the NMBRS integration.');

            return self::FAILURE;
        }

        $leaveBudget = Budget::whereHas('budgetType', fn ($q) => $q->where('id', BudgetType::LEAVE))->first();

        if ($leaveBudget === null) {
            $this->error('No leave budget found in Timatic.');

            return self::FAILURE;
        }

        $connector = new Connector($config['access_token']);

        $this->info('Fetching employee emails from NMBRS...');
        $emailsByEmployeeId = $this->fetchEmailsByEmployeeId($connector, $companyId);

        $this->info('Fetching approved leave requests from NMBRS...');
        $leaveRequests = $this->fetchLeaveRequests($connector, $companyId, $emailsByEmployeeId);

        $todayLeave = $leaveRequests->filter(fn (NmbrsLeaveRequest $leaveRequest) => $leaveRequest->isActiveToday());

        $this->info("Found {$todayLeave->count()} leave request(s) active today.");

        $today = CarbonImmutable::today('Europe/Amsterdam');
        $synced = 0;

        foreach ($todayLeave as $leaveRequest) {
            $employeeEmail = $emailsByEmployeeId->get($leaveRequest->employeeId);

            if (empty($employeeEmail)) {
                continue;
            }

            $user = User::query()->where('email', strtolower($employeeEmail))->first();

            if ($user === null) {
                $this->warn("No Timatic user found for {$employeeEmail} — skipping.");

                continue;
            }

            $alreadySynced = Entry::where('user_id', $user->id)
                ->where('budget_id', $leaveBudget->id)
                ->whereDate('started_at', $today->toDateString())
                ->exists();

            if ($alreadySynced) {
                $this->info("Leave for {$employeeEmail} already synced today — skipping.");

                continue;
            }

            $durationInMinutes = $leaveRequest->minutesPerDay();
            $startedAt = $today->setHour(9);
            $endedAt = $startedAt->addMinutes($durationInMinutes);

            Entry::create([
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_full_name' => $user->full_name,
                'budget_id' => $leaveBudget->id,
                'customer_id' => $leaveBudget->customer_id,
                'entry_type' => 'regular',
                'description' => __('nmbrs::nmbrs.leave_description'),
                'is_internal' => false,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ]);

            $this->info("Synced leave for {$employeeEmail} ({$durationInMinutes} min).");
            $synced++;
        }

        $this->info("Done. Synced {$synced} leave entry/entries.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<string, string> employeeId => businessEmail
     */
    private function fetchEmailsByEmployeeId(Connector $connector, string $companyId): Collection
    {
        $emails = collect();

        $connector->paginate(new GetPersonalInfoRequest($companyId))
            ->collect(throughItems: false)
            ->each(function (Response $response) use ($emails): void {
                $response->dto()
                    ->filter(fn (NmbrsEmployeePersonalInfo $info): bool => $info->businessEmail !== null)
                    ->each(fn (NmbrsEmployeePersonalInfo $info) => $emails->put($info->employeeId, strtolower((string) $info->businessEmail)));
            });

        return $emails;
    }

    /**
     * @param  Collection<string, string>  $emailsByEmployeeId
     * @return Collection<int, NmbrsLeaveRequest>
     */
    private function fetchLeaveRequests(Connector $connector, string $companyId, Collection $emailsByEmployeeId): Collection
    {
        $leaveRequests = collect();

        $connector->paginate(new GetLeaveRequestsRequest($companyId, now()->year))
            ->collect(throughItems: false)
            ->each(function (Response $response) use ($leaveRequests, $emailsByEmployeeId): void {
                $response->dto()
                    ->filter(fn (NmbrsLeaveRequest $leaveRequest): bool => $emailsByEmployeeId->has($leaveRequest->employeeId))
                    ->each(fn (NmbrsLeaveRequest $leaveRequest) => $leaveRequests->push($leaveRequest));
            });

        return $leaveRequests;
    }
}
