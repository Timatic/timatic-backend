<?php

namespace App\DataTransferObjects;

use App\Enums\HoursExportDimension;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

readonly class ExportParameters
{
    public function __construct(
        public ?int $year,
        public ?int $month,
        public ?string $fromMonth = null,
        public ?string $toMonth = null,
        public ?HoursExportDimension $groupBy = null,
        public ?HoursExportDimension $splitBy = null,
        public ?int $customerId = null,
        public ?int $budgetId = null,
        public ?int $userId = null,
    ) {}

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->requireYear(), $this->month ?? 1, 1)
            ?? throw new InvalidArgumentException('This export has an invalid period.');
    }

    public function end(): CarbonImmutable
    {
        return $this->month === null
            ? $this->start()->endOfYear()
            : $this->start()->endOfMonth();
    }

    public function requireYear(): int
    {
        return $this->year ?? throw new InvalidArgumentException('This export requires a year.');
    }

    public function requireMonth(): int
    {
        return $this->month ?? throw new InvalidArgumentException('This export requires a month.');
    }

    public function rangeStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', (string) $this->fromMonth, config('timatic.preferred_timezone'))
            ?? throw new InvalidArgumentException('This export requires a start month.');
    }

    public function rangeEnd(): CarbonImmutable
    {
        $toMonth = CarbonImmutable::createFromFormat('!Y-m', (string) $this->toMonth, config('timatic.preferred_timezone'))
            ?? throw new InvalidArgumentException('This export requires an end month.');

        return $toMonth->endOfMonth();
    }

    public function requireGroupBy(): HoursExportDimension
    {
        return $this->groupBy ?? throw new InvalidArgumentException('This export requires a group by dimension.');
    }
}
