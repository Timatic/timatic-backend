<?php

namespace Timatic\Tempo\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class DeletedWorklogs
{
    /** @param Collection<int, string> $worklogIds */
    public function __construct(
        public Collection $worklogIds,
        public int $nextSinceMilliseconds,
    ) {}
}
