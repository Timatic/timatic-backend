<?php

namespace App\Exports;

use App\DataTransferObjects\BudgetExportRow;
use App\Integrations\Contracts\ExportInterface;
use App\Models\Budget;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class BudgetsExport implements ExportInterface
{
    /**
     * @throws Exception
     */
    public function export(string $filePath): void
    {
        $writer = new Writer;
        $writer->openToFile($filePath);

        $writer->addRow(Row::fromValues($this->headings()));

        $this->collection($writer);

        $writer->close();
    }

    public function collection(Writer $writer): Collection
    {
        $rows = new Collection;

        Budget::query()
            ->with(['budgetVersions', 'budgetType', 'customer.accountManager'])
            ->orderBy('started_at', 'desc')
            ->chunk(100, function (Collection $budgets) use ($writer) {
                $rows = [];

                /** @var Budget $budget */
                foreach ($budgets as $budget) {
                    $period = $budget->getCurrentPeriodRelationData();

                    if (is_null($period)) {
                        continue;
                    }

                    if ($budget->customer?->account_manager_user_id) {
                        $accountManager = $budget->customer->accountManager;
                        $accountManagerName = $accountManager?->given_name.' '.$accountManager?->family_name;
                    }

                    assert(! is_null($budget->ended_at));
                    assert(! is_null($budget->customer));

                    $row = new BudgetExportRow(
                        id: $budget->id,
                        description: $budget->getTitle(),
                        customerId: $budget->customer->external_id,
                        customer: $budget->customer->name,
                        type: $budget->budgetType->title,
                        renewalFrequency: $budget->renewal_frequency,
                        startDate: $budget->started_at?->format('d-m-Y'),
                        expirationDate: $budget->ended_at->format('d-m-Y'),
                        totalHours: $this->formatDuration($period->getInitialMinutes()),
                        hoursRemaining: $this->formatDuration($period->getRemainingMinutes(true)),
                        accountManagerName: $accountManagerName ?? '',
                        changeId: $budget->activeVersion()->change_id,
                    );

                    $rows[] = Row::fromValues([
                        $row->id,
                        $row->description,
                        $row->customerId,
                        $row->customer,
                        $row->type,
                        $row->renewalFrequency,
                        $row->startDate,
                        $row->expirationDate,
                        $row->totalHours,
                        $row->hoursRemaining,
                        $row->accountManagerName,
                        $row->changeId,
                    ]);

                }
                $writer->addRows($rows);

            });

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Budget ID',
            'Description',
            'Customer ID',
            'Customer',
            'Type',
            'Renewal Frequency',
            'Start date',
            'Expiration date',
            'Total hours',
            'Hours remaining',
            'Account Manager',
            'Change ID',
        ];
    }

    public function title(): string
    {
        return 'Budgets';
    }

    /**
     * @throws Exception
     */
    private function formatDuration(int $minutes): string
    {
        $isNegative = false;

        if ($minutes < 0) {
            $isNegative = true;
        }

        $absoluteMinutes = abs($minutes);

        $hours = floor($absoluteMinutes / 60);

        $leftoverMinutes = $absoluteMinutes % 60;

        return sprintf(
            '%s%s:%s',
            $isNegative ? '-' : '',
            $hours,
            str_pad((string) $leftoverMinutes, 2, '0', STR_PAD_LEFT)
        );
    }
}
