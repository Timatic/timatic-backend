<?php

namespace Timatic\Jira;

use Timatic\Jira\DataTransferObjects\DeletedWorklogs;
use Timatic\Jira\DataTransferObjects\WorklogChanges;

/**
 * Where the worklogs of a Jira instance come from. Jira's own worklog feeds by default,
 * or an app such as Tempo that stores the real author while anonymising its Jira copy.
 */
interface WorklogFeed
{
    /** Value of external_source on the entries this feed produces. */
    public function externalSource(): string;

    public function changedSince(int $sinceMilliseconds): WorklogChanges;

    public function deletedSince(int $sinceMilliseconds): DeletedWorklogs;
}
