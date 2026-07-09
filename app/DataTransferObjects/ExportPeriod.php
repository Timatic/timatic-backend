<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

readonly class ExportPeriod
{
    public function __construct(
        public int $year,
        public ?int $month,
    ) {
        if (CarbonImmutable::create($this->year, $this->month ?? 1, 1) === null) {
            throw new InvalidArgumentException("Invalid date for year {$this->year} and month {$this->month}");
        }
    }

    public function start(): CarbonImmutable
    {
        $start = CarbonImmutable::create($this->year, $this->month ?? 1, 1);

        assert($start !== null);

        return $start;
    }

    public function end(): CarbonImmutable
    {
        return $this->month === null
            ? $this->start()->endOfYear()
            : $this->start()->endOfMonth();
    }

    public function requireMonth(): int
    {
        return $this->month ?? throw new InvalidArgumentException('This export requires a month.');
    }
}
