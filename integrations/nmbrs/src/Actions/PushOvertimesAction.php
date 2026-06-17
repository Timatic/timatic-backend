<?php

namespace Timatic\Nmbrs\Actions;

use App\Models\Integration;
use App\Models\Overtime;
use Illuminate\Support\Collection;
use RuntimeException;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployee;
use Timatic\Nmbrs\DataTransferObjects\NmbrsVariableHour;
use Timatic\Nmbrs\DataTransferObjects\OvertimeSyncResult;
use Timatic\Nmbrs\OAuthService;
use Timatic\Nmbrs\Requests\CreateVariableHourRequest;
use Timatic\Nmbrs\Requests\GetVariableHoursRequest;
use Timatic\Nmbrs\Services\NmbrsEmployeeService;
use Timatic\Nmbrs\Services\OvertimeTotalsService;

readonly class PushOvertimesAction
{
    public function __construct(
        private OAuthService $oauthService,
        private OvertimeTotalsService $totalsService,
    ) {}

    public function execute(): OvertimeSyncResult
    {
        $integration = Integration::where('type', 'nmbrs')->firstOrFail();
        $integration = $this->oauthService->refreshIfExpired($integration);

        $config = $integration->config ?? [];
        $companyId = $config['company_id'] ?? null;

        if (empty($companyId)) {
            throw new RuntimeException('Geen bedrijf geconfigureerd. Herstel de NMBRS koppeling.');
        }

        $connector = new Connector($config['access_token']);
        $employeeService = new NmbrsEmployeeService($connector, $companyId);
        $employeesByEmail = $employeeService->listByEmail();

        /** @var Collection<string, Collection<int, Overtime>> $overtimesByEmail */
        $overtimesByEmail = Overtime::isApproved(true)
            ->isExported(false)
            ->with('entry.user')
            ->get()
            ->groupBy(fn (Overtime $overtime) => strtolower($overtime->entry->user->email ?? ''))
            ->filter(fn (Collection $group, string $email) => ! empty($email));

        $warnings = [];
        $exportedCount = 0;

        foreach ($overtimesByEmail as $email => $overtimes) {
            /** @var NmbrsEmployee|null $employee */
            $employee = $employeesByEmail->get($email);

            if ($employee === null) {
                $warnings[] = "Medewerker {$email} niet gevonden in NMBRS — overgeslagen.";

                continue;
            }

            $type = $employee->isFulltime ? 'fulltime' : 'parttime';
            $hourCodes = $config['hour_codes'][$type] ?? [];
            $percentages = $this->totalsService->sumPerPercentage($overtimes);

            $missingPercentages = array_filter(array_keys($percentages), fn (int|string $percentage) => empty($hourCodes[$percentage]));

            if (! empty($missingPercentages)) {
                foreach ($missingPercentages as $percentage) {
                    $warnings[] = "Geen uurcode geconfigureerd voor {$type} {$percentage}% — {$email} overgeslagen.";
                }

                continue;
            }

            $existing = $connector->send(new GetVariableHoursRequest($employee->employeeId))->dto();

            foreach ($percentages as $percentage => $minutes) {
                $hourCode = $hourCodes[$percentage];

                if ($existing->contains(fn (NmbrsVariableHour $vh): bool => $vh->hourCode === $hourCode)) {
                    $warnings[] = "Uurcode {$hourCode} bestaat al voor {$email} — overgeslagen.";

                    continue;
                }

                $hours = round($minutes / 60, 2);
                $response = $connector->send(new CreateVariableHourRequest(
                    employeeId: $employee->employeeId,
                    hourCode: $hourCode,
                    hours: $hours
                ));

                if ($response->failed()) {
                    throw new RuntimeException("Fout bij synchroniseren van {$email}: ".$response->body());
                }
            }

            $overtimes->each(function (Overtime $overtime): void {
                $overtime->exported_at = now();
                $overtime->save();
            });

            $exportedCount += $overtimes->count();
        }

        return new OvertimeSyncResult(
            exportedCount: $exportedCount,
            warnings: $warnings,
        );
    }
}
