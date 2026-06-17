<?php

namespace App\Exports;

use App\DataTransferObjects\BudgetMutation;
use App\Models\Customer;
use App\Models\User;
use App\Services\BudgetUsageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class MonthlyBudgetsExport
{
    private Carbon $month;

    public function __construct(
        int $year,
        int $month,
        private BudgetUsageService $usageService,
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

        foreach ($this->collection() as $budgetMutation) {
            $writer->addRow(Row::fromValues($this->map($budgetMutation)));
        }
        $writer->close();

    }

    /**
     * @return list<bool|float|int|string|null>
     */
    public function map(BudgetMutation $row): array
    {
        assert(! is_null($row->budget->ended_at));
        assert(! is_null($row->budget->customer));

        $accountManagerName = '';
        if ($row->accountManager) {
            $accountManagerName = $row->accountManager->given_name.' '.$row->accountManager->family_name;
        }

        return [
            $row->budget->id,
            $row->budget->budgetType->title,
            $row->budget->customer->external_id,
            $row->customerName,
            $row->budgetTitle,
            $row->budget->renewal_frequency,
            $row->budget->started_at?->format('d-m-Y'),
            $row->budget->ended_at->format('d-m-Y'),
            (bool) $row->budget->archived_at?->isBefore($this->month->endOfMonth()),
            $row->budget->getHourlyRateBigDecimal()->toFloat(),
            (float) $row->budget->getTotalPrice(),
            $row->startBalance->toFloat(),
            $row->expiredCredit->toFloat(),
            $row->renewedCredit->toFloat(),
            $row->usedCredit->toFloat(),
            $row->usedOutOfBudget->toFloat(),
            $row->endBalance->toFloat(),
            $accountManagerName,
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'budget id',
            'budget type',
            'customer external ID',
            'customer name',
            'budget title',
            'renewal frequency',
            'start date',
            'end date',
            'is archived',
            'hourly rate',
            'initial credit',
            'balance on '.$this->month->clone()->firstOfMonth()->format('d-m'),
            'expired credit',
            'renewed credit',
            'used credit',
            'used out of budget',
            'balance on '.$this->month->clone()->lastOfMonth()->format('d-m'),
            'Service manager',
        ];
    }

    /**
     * @return Collection<int, BudgetMutation>
     */
    public function collection(): Collection
    {
        $budgetMutations = $this->usageService->get($this->month);

        $users = User::all();

        $budgetMutations->transform(function (BudgetMutation $budgetMutation) use ($users) {
            /** @var Customer $customer */
            $customer = $budgetMutation->budget->customer;
            $budgetMutation->customerName = $customer->name ?? '';
            $budgetMutation->budgetTitle = $budgetMutation->budget->getTitle();
            $budgetMutation->accountManager = $users->firstWhere('id', $customer->account_manager_user_id);

            return $budgetMutation;
        });

        return $budgetMutations;
    }
}
