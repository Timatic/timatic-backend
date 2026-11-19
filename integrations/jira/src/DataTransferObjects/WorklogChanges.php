<?php

namespace Timatic\Jira\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class WorklogChanges
{
    /** @param Collection<int, JiraWorklog> $worklogs */
    public function __construct(
        public Collection $worklogs,
        public int $nextSinceMilliseconds,
    ) {}
}
