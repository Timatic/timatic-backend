<?php

namespace Timatic\Tempo\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class TempoDeletedWorklogPage
{
    /** @param Collection<int, string> $worklogIds */
    public function __construct(
        public Collection $worklogIds,
        public ?CarbonImmutable $latestDeletedAt,
        public ?string $nextKey,
    ) {}
}
