<?php

declare(strict_types=1);

namespace Timatic\Tempo\DataTransferObjects;

/** One sync from Tempo to entries; carries the position from page to page in the job payload. */
readonly class WorklogSyncRun
{
    public function __construct(
        public string $id,
        public int $integrationId,
        public int $updatedSinceMilliseconds,
        public int $deletedSinceMilliseconds,
        public int $startedAtMilliseconds,
        public int $latestUpdatedMilliseconds,
        public int $page = 1,
    ) {}

    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
