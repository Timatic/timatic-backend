<?php

namespace Timatic\Jira\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class DeletedWorklogs
{
    /** @param Collection<int, string> $worklogIds */
    public function __construct(
        public Collection $worklogIds,
        public int $nextSinceMilliseconds,
    ) {}
}
