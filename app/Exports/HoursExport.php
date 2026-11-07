<?php

declare(strict_types=1);

namespace App\Exports;

use App\DataTransferObjects\ExportParameters;
use App\DataTransferObjects\HoursExportRow;
use App\Enums\HoursExportDimension;
use App\Integrations\Exports\ExportInterface;
use App\Models\Entry;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class HoursExport implements ExportInterface
{
    public const string KEY = 'hours-excel';

    public function __construct(
        private ExportParameters $period,
    ) {}

    public function export(string $filePath): void
    {
        $writer = new Writer;
        $writer->openToFile($filePath);

        $writer->addRow(Row::fromValues($this->headings()));

        foreach ($this->collection() as $row) {
            $writer->addRow(Row::fromValues([
                $row->group,
                ...($this->period->splitBy ? [$row->split] : []),
                ...$row->hoursPerMonth,
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
            $this->period->requireGroupBy()->label(),
            ...($this->period->splitBy ? [$this->period->splitBy->label()] : []),
            ...$this->months()->all(),
        ];
    }

    /**
     * @return Collection<int, HoursExportRow>
     */
    public function collection(): Collection
    {
        $groupBy = $this->period->requireGroupBy();
        $splitBy = $this->period->splitBy;
        $months = $this->months();
        $timezone = config('timatic.preferred_timezone');

        $budgetIsDimension = in_array(HoursExportDimension::Budget, [$groupBy, $splitBy], true);

        return Entry::query()
            ->select(['user_id', 'user_full_name', 'budget_id', 'is_internal', 'customer_id', 'customer_name', 'ticket_type', 'started_at', 'minutes_spent'])
            ->when($budgetIsDimension, fn (Builder $query) => $query->with('budget.budgetVersions'))
            ->whereBetween('started_at', [
                $this->period->rangeStart()->setTimezone(config('app.timezone')),
                $this->period->rangeEnd()->setTimezone(config('app.timezone')),
            ])
            ->when($this->period->customerId, fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($this->period->budgetId, fn (Builder $query, int $budgetId) => $query->where('budget_id', $budgetId))
            ->when($this->period->userId, fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            // Latest first, so a row is labelled with the most recent name on its entries.
            ->orderByDesc('started_at')
            ->get()
            ->groupBy(fn (Entry $entry) => $groupBy->keyOf($entry).'|'.$splitBy?->keyOf($entry))
            ->map(function (Collection $entries) use ($groupBy, $splitBy, $months, $timezone) {
                $minutesPerMonth = $entries
                    ->groupBy(fn (Entry $entry) => $entry->started_at->copy()->setTimezone($timezone)->format('Y-m'))
                    ->map(fn (Collection $entriesInMonth) => $entriesInMonth->sum('minutes_spent'));

                $firstEntry = $entries->firstOrFail();

                return new HoursExportRow(
                    group: $groupBy->labelOf($firstEntry),
                    split: $splitBy?->labelOf($firstEntry),
                    hoursPerMonth: $months->map(fn (string $month) => round($minutesPerMonth->get($month, 0) / 60, 2))->values()->all(),
                );
            })
            // Corrections can cancel out all minutes of a row.
            ->reject(fn (HoursExportRow $row) => collect($row->hoursPerMonth)->every(fn (float $hours) => $hours == 0))
            ->sortBy(fn (HoursExportRow $row) => [$row->group, $row->split])
            ->values();
    }

    /**
     * @return Collection<int, non-falsy-string>
     */
    public function months(): Collection
    {
        return collect(CarbonPeriod::create($this->period->rangeStart(), '1 month', $this->period->rangeEnd())->toArray())
            ->map(fn (CarbonInterface $month): string => $month->format('Y-m'))
            ->values();
    }
}
