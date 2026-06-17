<?php

namespace App\Exports;

use App\DataTransferObjects\BudgetEntryExportRow;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class BudgetEntriesExport
{
    /**
     * BudgetEntriesExport constructor.
     *
     * @param  Collection<int, Entry>  $entries
     */
    public function __construct(
        protected Collection $entries
    ) {}

    public function export(string $filePath): void
    {
        $writer = new Writer;
        $writer->openToBrowser($filePath);

        $writer->addRow(Row::fromValues($this->headings()));

        foreach ($this->collection() as $row) {
            $writer->addRow(Row::fromValues([
                $row->entryId,
                $row->date,
                $row->ticketNumber,
                $row->ticketTitle,
                $row->description,
                $row->user,
                $row->overtime,
                $row->minutesSpent,
                $row->startedAt,
                $row->endedAt,
                $row->createdAt,
                $row->updatedAt,
            ]));
        }
        $writer->close();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Entry ID',
            'Date',
            'Ticket Number',
            'Ticket',
            'Description',
            'User',
            'Overtime',
            'Minutes spent',
            'Started at',
            'Ended At',
            'Created At',
            'Last Updated At',
        ];
    }

    /**
     * @return Collection<int, BudgetEntryExportRow>
     */
    public function collection(): Collection
    {
        return $this->entries->map(function (Entry $entry) {
            /** @var Carbon $localizedStartedAt */
            $localizedStartedAt = $entry->started_at->copy()->tz(config('timatic.preferred_timezone'));
            /** @var Carbon $localizedEndedAt */
            $localizedEndedAt = $entry->ended_at->copy()->tz(config('timatic.preferred_timezone'));
            /** @var Carbon $localizedCreatedAt */
            $localizedCreatedAt = $entry->created_at->copy()->tz(config('timatic.preferred_timezone'));
            /** @var ?Carbon $localizedUpdatedAt */
            $localizedUpdatedAt = $entry->updated_at?->copy()->tz(config('timatic.preferred_timezone'));

            return new BudgetEntryExportRow(
                entryId: (string) $entry->id,
                date: $localizedStartedAt->toDateString(),
                ticketNumber: $entry->ticket_number,
                ticketTitle: $entry->ticket_title,
                description: $entry->description,
                user: $entry->user_full_name,
                overtime: $entry->customerOvertime ? 'Yes' : 'No',
                minutesSpent: (string) $entry->minutes_spent,
                startedAt: $localizedStartedAt->toDateTimeString(),
                endedAt: $localizedEndedAt->toDateTimeString(),
                createdAt: $localizedCreatedAt->toDateTimeString(),
                updatedAt: $localizedUpdatedAt?->toDateTimeString()
            );
        });
    }
}
