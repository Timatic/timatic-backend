<?php

namespace Timatic\Jira;

use Timatic\Jira\DataTransferObjects\DeletedWorklogs;
use Timatic\Jira\DataTransferObjects\WorklogChanges;

/**
 * Where the worklogs of a Jira instance come from, e.g. Tempo, which stores
 * the real author that it strips from its Jira copies.
 */
interface WorklogFeed
{
    public function changedSince(int $sinceMilliseconds): WorklogChanges;

    public function deletedSince(int $sinceMilliseconds): DeletedWorklogs;
}
