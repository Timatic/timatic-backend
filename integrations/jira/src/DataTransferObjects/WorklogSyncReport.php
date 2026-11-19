<?php

namespace Timatic\Jira\DataTransferObjects;

class WorklogSyncReport
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $deleted = 0,
        public int $skipped = 0,
        public int $nextUpdatedSinceMilliseconds = 0,
        public int $nextDeletedSinceMilliseconds = 0,
    ) {}
}
