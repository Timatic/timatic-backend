<?php

declare(strict_types=1);

namespace Timatic\Tempo\Exceptions;

use RuntimeException;

class WorklogSyncNotStartedException extends RuntimeException
{
    public static function notConnected(): self
    {
        return new self('Tempo token or connected Jira instance missing, nothing to sync.');
    }

    public static function alreadyRunning(): self
    {
        return new self('A worklog sync is already queued or running.');
    }
}
