<?php

namespace Timatic\Tempo\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class TempoWorklogPage
{
    /** @param Collection<int, TempoWorklog> $worklogs */
    public function __construct(
        public Collection $worklogs,
        public ?CarbonImmutable $latestUpdatedAt,
        public bool $hasNext,
    ) {}
}
