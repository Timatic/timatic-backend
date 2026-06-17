<?php

namespace App\Exports;

use App\Models\Entry;
use App\Models\User;
use App\Queries\HoursPerUserPerMonth;
use App\Queries\UnusedSuggestionsPerUserPerMonth;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class UsersMonthlySummaryExport
{
    public function __construct(
        private CarbonInterface $start,
        private CarbonInterface $end,
    ) {}

    public function export(string $filePath): void
    {
        $writer = new Writer;

        $writer->openToFile($filePath);

        $writer->addRow(Row::fromValues($this->headings()));

        foreach ($this->collection() as $row) {
            $writer->addRow(Row::fromValues($this->map($row)));
        }
        $writer->close();
    }

    public function collection(): Collection
    {
        $users = User::withTrashed()->get();
        $unusedSuggestionRecords = UnusedSuggestionsPerUserPerMonth::query($this->start, $this->end)->get();

        return HoursPerUserPerMonth::query($this->start, $this->end)->get()
            ->map(function ($userStatsRecord) use ($users, $unusedSuggestionRecords) {
                /** @var Entry $userStatsRecord */
                $user = $users->filter(function ($item) use ($userStatsRecord) {
                    /** @var User $item */
                    return strtolower((string) $item->email) == strtolower((string) $userStatsRecord->user_email);
                })->first();
                if ($user) {
                    $userStatsRecord->user_team = $user->team?->name;
                }

                $unusedSuggestionRecord = $unusedSuggestionRecords->firstWhere('user_id', $userStatsRecord->user_id);
                $userStatsRecord->unused_suggestions = $unusedSuggestionRecord->unused_suggestions ?? '0';

                return $userStatsRecord;
            });
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'User ID',
            'Full name',
            'Email',
            'Team',
            'Total hours',
            'Based on suggestions',
            'Unused from suggestions',
            'Internal - '.config('timatic.tenant_name'),
            'Internal - Customers',
            'On budgets',
            'Paid per hour',
        ];
    }

    /**
     * @param  mixed  $row
     * @return list<bool|float|int|string|null>
     */
    public function map($row): array
    {
        return
        [
            $row->user_id,
            $row->user_full_name,
            $row->user_email,
            $row->user_team,
            (float) $row->total,
            (float) $row->based_on_suggestions,
            (float) $row->unused_suggestions,
            (float) $row->internal_tenant,
            (float) $row->internal_customers,
            (float) $row->on_budgets,
            (float) $row->paid_per_hour,
        ];
    }
}
