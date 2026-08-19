<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Spatie\Period\Boundaries;
use Spatie\Period\Period;
use Spatie\Period\Precision;

class TimeSlot extends Period
{
    public readonly CarbonInterface $startedAt;

    public readonly CarbonInterface $endedAt;

    public function __construct(
        DateTimeInterface $start,
        DateTimeInterface $end,
        ?Precision $precision = null,
        ?Boundaries $boundaries = null,
    ) {
        parent::__construct(
            $start instanceof DateTimeImmutable ? $start : DateTimeImmutable::createFromInterface($start),
            $end instanceof DateTimeImmutable ? $end : DateTimeImmutable::createFromInterface($end),
            $precision ?? Precision::SECOND(),
            $boundaries ?? Boundaries::EXCLUDE_END(),
        );

        $this->startedAt = $start instanceof CarbonInterface ? $start : Carbon::instance($start);
        $this->endedAt = $end instanceof CarbonInterface ? $end : Carbon::instance($end);
    }
}
