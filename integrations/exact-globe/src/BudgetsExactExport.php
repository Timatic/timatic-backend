<?php

namespace Timatic\ExactGlobe;

use App\DataTransferObjects\BudgetMutation;
use App\Integrations\Contracts\ExportInterface;
use App\Services\BudgetUsageService;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer;
use Timatic\ExactGlobe\DataTransferObjects\LedgerMapping;
use Timatic\ExactGlobe\DataTransferObjects\MutationRow;

class BudgetsExactExport implements ExportInterface
{
    private Carbon $month;

    /**
     * @param  Collection<string, LedgerMapping>  $ledgerMappings  keyed by budget type id
     */
    public function __construct(
        int $year,
        int $month,
        private BudgetUsageService $usageService,
        private Collection $ledgerMappings,
    ) {
        /** @var Carbon $firstOfMonth */
        $firstOfMonth = Carbon::create($year, $month, 1);
        $this->month = $firstOfMonth;
    }

    public function export(string $filePath): void
    {
        $writer = new Writer;
        $writer->openToFile($filePath);

        $writer->addRow(Row::fromValues($this->headings()));

        foreach ($this->rows() as $row) {
            $writer->addRow(Row::fromValues($this->map($row)));
        }

        $writer->close();
    }

    /**
     * @return list<bool|float|int|string>
     */
    public function map(MutationRow $row): array
    {
        $amount = ($row->amount->isPositive() ? '+' : '').str_replace('.', ',', (string) $row->amount);
        $lastDayOfMonth = $this->month->clone()->lastOfMonth()->format('tmY');
        $fullDescription = $row->description.' '.$this->month->format('F Y').' - '.$row->budgetId;

        return [
            $row->index,
            'M', // dagboekType
            '90', // dagboekNr
            $this->month->format('m'),
            $this->month->format('Y'),
            '',
            $fullDescription,
            $lastDayOfMonth,
            $this->ledgerId($row),
            $row->customerId ?? '',
            '',
            '',
            $amount,
            '',
            'EUR',
            '1',
            '',
            '',
            '',
            '',
            '0',
            '0,00',
            '',
            '',
            '',
            '',
            ($row->credit ? 10 : 20), // kostplaatsCode
            '',
            '0,00',
            '',
            '',
            'N',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            '0',
            'M',
            '90',
            $this->month->format('m'),
            $this->month->format('Y'),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '0,00',
            '',
            '',
            '',
            'K',
            '0,00',
            '',
            '',
            '',
            '',
            '',
            '',
            'B',
            '',
            '',
            '',
            '',
            '',
            '',
            'N',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * @return Collection<int, MutationRow>
     */
    public function rows(): Collection
    {
        /** @var Collection<int, MutationRow> $rows */
        $rows = new Collection;

        $budgetUsage = (new Collection($this->usageService->get($this->month)))
            ->filter(fn (BudgetMutation $usage) => $this->ledgerMappings->has($usage->budget->budget_type_id));

        foreach ($budgetUsage as $budgetMutation) {
            $this->addRowPairToCollection('Verbruik', $budgetMutation->usedCredit, $budgetMutation, $rows);
        }

        foreach ($budgetUsage as $budgetMutation) {
            $this->addRowPairToCollection('Vrijval', $budgetMutation->expiredCredit, $budgetMutation, $rows);
        }

        return $rows;
    }

    private function ledgerId(MutationRow $row): string
    {
        /** @var LedgerMapping $mapping */
        $mapping = $this->ledgerMappings->get($row->budgetTypeId);

        return match ($row->description) {
            'Verbruik' => $row->credit ? $mapping->verbruikCreditLedgerId : $mapping->verbruikDebitLedgerId,
            default => $row->credit ? $mapping->vrijvalCreditLedgerId : $mapping->vrijvalDebitLedgerId,
        };
    }

    /**
     * @param  Collection<int, MutationRow>  $rows
     */
    private function addRowPairToCollection(
        string $description,
        BigDecimal $amount,
        BudgetMutation $budgetMutation,
        Collection $rows,
    ): void {
        if ($amount->isEqualTo(0)) {
            return;
        }

        $creditRow = new MutationRow(
            index: $rows->count() + 1,
            description: $description,
            amount: $amount,
            customerId: $budgetMutation->budget->customer?->external_id,
            budgetTypeId: $budgetMutation->budget->budget_type_id,
            budgetId: $budgetMutation->budget->id,
            credit: true,
        );

        $debitRow = new MutationRow(
            index: $rows->count() + 2,
            description: $description,
            amount: $amount->multipliedBy(-1),
            customerId: $creditRow->customerId,
            budgetTypeId: $creditRow->budgetTypeId,
            budgetId: $creditRow->budgetId,
            credit: false,
        );

        $rows->push($creditRow);
        $rows->push($debitRow);
    }
}
