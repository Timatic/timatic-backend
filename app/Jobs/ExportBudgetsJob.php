<?php

namespace App\Jobs;

use App\Exports\BudgetsExport;
use App\Exports\EntriesExport;
use App\Exports\MonthlyBudgetsExport;
use App\Exports\UsersMonthlySummaryExport;
use App\Mail\ExportEmail;
use App\Models\User;
use App\Services\BudgetUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

class ExportBudgetsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected User $user;

    protected string $exportType;

    protected int $year;

    protected ?int $month;

    protected BudgetUsageService $usageService;

    public function __construct(User $user, string $exportType, int $year, ?int $month, BudgetUsageService $usageService)
    {
        $this->user = $user;
        $this->exportType = $exportType;
        $this->year = $year;
        $this->month = $month;
        $this->usageService = $usageService;
    }

    public function handle(): void
    {
        $fileName = $this->generateFileName();
        $this->exportData($fileName);
        $this->sendExportEmail($fileName);
    }

    private function generateFileName(): string
    {
        return "export_{$this->exportType}_{$this->year}_".($this->month ?? 'all').'.xlsx';
    }

    private function exportData(string $fileName): void
    {
        $start = CarbonImmutable::create($this->year, $this->month ?? 1, 1);
        if ($start === null) {
            throw new InvalidArgumentException("Invalid date for year {$this->year} and month {$this->month}");
        }

        if ($this->month === null) {
            $end = $start->endOfYear();
        } else {
            $end = $start->endOfMonth();
        }

        $localFilePath = Storage::disk('temp')->path($fileName);

        switch ($this->exportType) {
            case 'budgets-monthly-excel':
                Assert::notNull($this->month);
                (new MonthlyBudgetsExport($this->year, $this->month, $this->usageService))->export($localFilePath);
                break;

            case 'budgets-excel':
                (new BudgetsExport)->export($localFilePath);
                break;

            case 'entries-excel':
                (new EntriesExport($start, $end))->export($localFilePath);
                break;

            case 'users-monthly-summary-excel':
                (new UsersMonthlySummaryExport($start, $end))->export($localFilePath);
                break;
            default:
                throw new InvalidArgumentException("Invalid export type: {$this->exportType}");
        }

        $content = Storage::disk('temp')->get($fileName);

        if ($content !== null) {
            Storage::disk('s3')->put($fileName, $content);
        } else {
            throw new InvalidArgumentException("File {$fileName} does not exist");
        }

    }

    private function sendExportEmail(string $fileName): void
    {
        Mail::to($this->user->email)->queue(new ExportEmail($fileName, $this->year, $this->month, $this->exportType));
    }
}
