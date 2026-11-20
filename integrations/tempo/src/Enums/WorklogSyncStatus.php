<?php

declare(strict_types=1);

namespace Timatic\Tempo\Enums;

enum WorklogSyncStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';
}
