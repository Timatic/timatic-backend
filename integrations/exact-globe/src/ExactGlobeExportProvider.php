<?php

namespace Timatic\ExactGlobe;

use App\DataTransferObjects\ExportFormat;
use App\DataTransferObjects\ExportPeriod;
use App\Enums\ExportDateRequirement;
use App\Integrations\Contracts\ExportInterface;
use App\Integrations\Contracts\ExportProviderInterface;
use App\Services\BudgetUsageService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Timatic\ExactGlobe\DataTransferObjects\LedgerMapping;

final class ExactGlobeExportProvider implements ExportProviderInterface
{
    public const EXPORT_KEY = 'exact-globe-csv';

    /**
     * @param  Collection<string, LedgerMapping>  $ledgerMappings  keyed by budget type id
     */
    public function __construct(private readonly Collection $ledgerMappings) {}

    public function exportFormats(): Collection
    {
        if ($this->ledgerMappings->isEmpty()) {
            return new Collection;
        }

        return new Collection([
            new ExportFormat(self::EXPORT_KEY, 'Budget mutations - Exact CSV', ExportDateRequirement::Monthly, 'csv'),
        ]);
    }

    public function createExport(string $key, ExportPeriod $period): ExportInterface
    {
        if ($key !== self::EXPORT_KEY) {
            throw new InvalidArgumentException("Unknown export format: {$key}");
        }

        return new BudgetsExactExport(
            $period->year,
            $period->requireMonth(),
            app(BudgetUsageService::class),
            $this->ledgerMappings,
        );
    }

    public static function fromConfig(array $config): static
    {
        /** @var array<string, array<string, mixed>> $mappingRows */
        $mappingRows = $config['ledger_mapping'] ?? [];

        $ledgerMappings = (new Collection($mappingRows))
            ->map(fn (array $row, string $budgetTypeId) => LedgerMapping::fromConfigRow($budgetTypeId, $row));

        return new self($ledgerMappings);
    }
}
