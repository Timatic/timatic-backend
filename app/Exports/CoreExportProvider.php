<?php

namespace App\Exports;

use App\DataTransferObjects\ExportFormat;
use App\DataTransferObjects\ExportPeriod;
use App\Enums\ExportDateRequirement;
use App\Integrations\Contracts\ExportInterface;
use App\Integrations\Contracts\ExportProviderInterface;
use App\Services\BudgetUsageService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class CoreExportProvider implements ExportProviderInterface
{
    public function exportFormats(): Collection
    {
        return collect([
            new ExportFormat('budgets-monthly-excel', 'Budget mutations - Excel', ExportDateRequirement::Monthly),
            new ExportFormat('budgets-excel', 'Budgets current balance', ExportDateRequirement::None),
            new ExportFormat('entries-excel', 'Entries dump', ExportDateRequirement::MonthlyAndYearly),
            new ExportFormat('users-monthly-summary-excel', 'Monthly summary per user', ExportDateRequirement::Monthly),
        ]);
    }

    public function createExport(string $key, ExportPeriod $period): ExportInterface
    {
        return match ($key) {
            'budgets-monthly-excel' => new MonthlyBudgetsExport($period->year, $period->requireMonth(), app(BudgetUsageService::class)),
            'budgets-excel' => new BudgetsExport,
            'entries-excel' => new EntriesExport($period->start(), $period->end()),
            'users-monthly-summary-excel' => new UsersMonthlySummaryExport($period->start(), $period->end()),
            default => throw new InvalidArgumentException("Unknown export format: {$key}"),
        };
    }

    public static function fromConfig(array $config): static
    {
        return new self;
    }
}
