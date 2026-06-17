<?php

namespace Timatic\Nmbrs\Commands;

use App\Models\Integration;
use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\Mail\OvertimesEngineerMail;
use Timatic\Nmbrs\Mail\OvertimesManagementMail;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Services\NmbrsEmployeeService;
use Timatic\Nmbrs\Services\OvertimeTotalsService;

class SendOvertimeEmailsCommand extends Command
{
    protected $signature = 'nmbrs:send-overtime-emails';

    protected $description = 'Send monthly overtime emails to management and individual engineers';

    public function handle(OAuthService $oauthService, OvertimeTotalsService $totalsService): int
    {
        $integration = Integration::where('type', 'nmbrs')->firstOrFail();
        $integration = $oauthService->refreshIfExpired($integration);

        $config = $integration->config ?? [];
        $companyId = $config['company_id'] ?? null;

        if (empty($companyId)) {
            $this->error('No company_id configured. Reconnect the NMBRS integration.');

            return self::FAILURE;
        }

        $connector = new Connector($config['access_token']);
        $employeeService = new NmbrsEmployeeService($connector, $companyId);

        $this->info('Fetching employees from NMBRS...');
        $employeesByEmail = $employeeService->listByEmail();

        $previousMonth = Carbon::today()->subMonth();

        /** @var Collection<string, Collection<int, Overtime>> $overtimesByEmail */
        $overtimesByEmail = Overtime::isApproved(true)
            ->isExported(false)
            ->with('entry.user')
            ->get()
            ->groupBy(fn (Overtime $overtime) => strtolower($overtime->entry->user_email ?? ''))
            ->filter(fn (Collection $group, string $email) => ! empty($email));

        // Management email
        $managementEmails = array_filter(
            array_map('trim', explode("\n", $config['management_emails'] ?? ''))
        );

        if (! empty($managementEmails)) {
            $overtimeSummary = $overtimesByEmail->map(
                fn (Collection $overtimes) => $totalsService->sumPerPercentage($overtimes)
            );

            Mail::to($managementEmails)->send(
                new OvertimesManagementMail($previousMonth, $overtimeSummary, $employeesByEmail)
            );

            $this->info('Management email sent to: '.implode(', ', $managementEmails));
        } else {
            $this->warn('No management emails configured — skipping management email.');
        }

        // Engineer emails
        foreach ($overtimesByEmail as $email => $overtimes) {
            $employee = $employeesByEmail->get($email);

            if ($employee === null) {
                $this->warn("User {$email} not found in NMBRS — skipping engineer email.");

                continue;
            }

            $percentages = $totalsService->sumPerPercentage($overtimes);
            $fullName = $overtimes->first()?->entry->user_full_name ?? $email;

            Mail::to($email)->send(
                new OvertimesEngineerMail($previousMonth, $fullName, $percentages)
            );

            $this->info("Engineer email sent to: {$email}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
