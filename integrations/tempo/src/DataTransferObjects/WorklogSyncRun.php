<?php

declare(strict_types=1);

namespace Timatic\Tempo\DataTransferObjects;

use Timatic\Tempo\Enums\WorklogSyncStatus;

/** One sync from Tempo to entries; travels from page to page in the job payload and is shown as progress. */
readonly class WorklogSyncRun
{
    public function __construct(
        public string $id,
        public int $integrationId,
        public WorklogSyncStatus $status,
        public int $updatedSinceMilliseconds,
        public int $deletedSinceMilliseconds,
        public int $startedAtMilliseconds,
        public int $latestUpdatedMilliseconds,
        public int $page = 1,
        public int $processed = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $deleted = 0,
        public int $skipped = 0,
        public ?int $firstWorklogStartedAtMilliseconds = null,
        public ?int $currentWorklogStartedAtMilliseconds = null,
        public ?string $failure = null,
    ) {}

    public function with(mixed ...$changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /** Tempo returns worklogs oldest first, so the start date of the current worklog tells how far the run is. */
    public function progressPercentage(): ?int
    {
        if ($this->firstWorklogStartedAtMilliseconds === null || $this->currentWorklogStartedAtMilliseconds === null) {
            return null;
        }

        $span = $this->startedAtMilliseconds - $this->firstWorklogStartedAtMilliseconds;

        if ($span <= 0) {
            return 100;
        }

        return (int) max(0, min(100, round(($this->currentWorklogStartedAtMilliseconds - $this->firstWorklogStartedAtMilliseconds) / $span * 100)));
    }
}
