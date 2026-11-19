<?php

namespace Timatic\Jira\DataTransferObjects;

readonly class WorklogSyncReport
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $deleted,
        public int $skipped,
        public int $nextUpdatedSinceMilliseconds,
        public int $nextDeletedSinceMilliseconds,
    ) {}
}
