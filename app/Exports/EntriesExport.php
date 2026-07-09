<?php

namespace App\Exports;

use App\DataTransferObjects\EntryExportRow;
use App\Integrations\Contracts\ExportInterface;
use App\Models\Entry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class EntriesExport implements ExportInterface
{
    private CarbonInterface $start;

    private CarbonInterface $end;

    public function __construct(CarbonInterface $start, CarbonInterface $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function export(string $filePath): void
    {
        $writer = new Writer;
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($this->headings()));
        $this->collection()->chunk(100)->each(function ($rows) use ($writer) {
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([
                    $row->startedAt,
                    $row->settlement,
                    $row->ticketNumber,
                    $row->ticketType,
                    $row->customerExternalId,
                    $row->customerName,
                    $row->budgetId,
                    $row->budgetTitle,
                    $row->budgetTypeId,
                    $row->employeeName,
                    $row->employeeTeam,
                    $row->description,
                    $row->invoicedAt,
                    $row->hourlyRate,
                    $row->hoursSpent,
                    $row->result,
                ]));
            }
        });
        $writer->close();
    }

    public function collection(): Collection
    {
        $entries = Entry::query()
            ->whereBetween('started_at', [$this->start, $this->end])
            ->with('budget')
            ->orderBy('started_at')
            ->get();

        $users = User::all();

        $rows = new Collection;

        foreach ($entries as $entry) {
            $row = new EntryExportRow;
            $row->startedAt = $entry->started_at->format('d-m-Y');
            $row->ticketNumber = $entry->ticket_number;
            $row->ticketType = $entry->ticket_type;
            assert(! is_null($entry->customer) && ! is_null($entry->customer->external_id));
            $row->customerExternalId = $entry->customer->external_id;
            $row->customerName = $entry->customer_name;
            $row->budgetId = (string) $entry->budget_id;
            $row->employeeName = $entry->user_full_name;

            $hourlyRate = BigDecimal::of($entry->hourly_rate ?? 0);

            if ($entry->invoiced_at) {
                $row->invoicedAt = $entry->invoiced_at->format('d-m-Y');
            }

            if ($entry->is_internal) {
                $row->settlement = 'Intern';
                $hourlyRate = BigDecimal::of(0);
            } elseif (! is_null($entry->budget_id)) {
                if ($entry->budget?->ended_at?->isBefore($entry->started_at)) {
                    $row->settlement = 'Factuur (verlopen tegoed)';
                } else {
                    $row->settlement = 'Tegoed';
                }
            } else {
                $row->settlement = 'Factuur';
            }

            $hoursSpent = BigDecimal::of($entry->minutes_spent ?? 0)
                ->dividedBy(60, 4, RoundingMode::HALF_UP);
            $row->hoursSpent = $hoursSpent->toFloat();

            if ($entry->budget) {
                $row->budgetTitle = $entry->budget->getTitle($entry->started_at);
                $row->budgetTypeId = $entry->budget->budget_type_id;
                $hourlyRate = $entry->budget->getHourlyRateBigDecimal($entry->started_at);
            }

            $row->hourlyRate = $hourlyRate->toFloat();
            $row->result = $hoursSpent->multipliedBy($hourlyRate)->toFloat();

            $row->employeeName = $entry->user_full_name;

            /** @var ?User $user */
            $user = $users->filter(function ($item) use ($entry) {
                assert(! is_null($item->email));

                return strtolower($item->email) == strtolower((string) $entry->user_email);
            })->first();
            if ($user) {
                $row->employeeTeam = $user->team?->name;
            }
            $row->description = $entry->description;

            $rows->push($row);
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Started At',
            'Settlement',
            'Ticket Number',
            'Ticket Type',
            'Customer External ID',
            'Customer Name',
            'Budget ID',
            'Budget Title',
            'Budget Type',
            'Employee',
            'Team',
            'Description',
            'Invoiced At',
            'Hourly Rate',
            'Time Charged',
            'Total',
        ];
    }

    public function title(): string
    {
        return 'Entries';
    }
}
