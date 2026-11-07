<?php

namespace App\DataTransferObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

readonly class ExportPeriod
{
    public function __construct(
        public ?int $year,
        public ?int $month,
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
}
