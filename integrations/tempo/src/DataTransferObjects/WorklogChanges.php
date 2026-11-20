<?php

namespace Timatic\Tempo\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class WorklogChanges
{
    /** @param Collection<int, TempoWorklog> $worklogs */
    public function __construct(
        public Collection $worklogs,
        public int $nextSinceMilliseconds,
    ) {}
}
