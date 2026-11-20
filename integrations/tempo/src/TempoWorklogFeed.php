<?php

namespace Timatic\Tempo;

use Carbon\CarbonImmutable;
use Timatic\Tempo\DataTransferObjects\DeletedWorklogs;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklogPage;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPage;
use Timatic\Tempo\DataTransferObjects\WorklogChanges;
use Timatic\Tempo\Requests\GetChangedWorklogsRequest;
use Timatic\Tempo\Requests\GetDeletedWorklogsRequest;

/**
 * Reads worklogs from Tempo, which keeps the real author and description
 * that Tempo strips from its Jira copies.
 */
final class TempoWorklogFeed
{
    public function __construct(private readonly Connector $connector) {}

    public function changedSince(int $sinceMilliseconds): WorklogChanges
    {
        $since = CarbonImmutable::createFromTimestampMs($sinceMilliseconds);
        $worklogs = collect();
        $latest = $since;
        $offset = 0;

        do {
            /** @var TempoWorklogPage $page */
            $page = $this->connector->send(new GetChangedWorklogsRequest($since, $offset))->dto();
            $worklogs = $worklogs->merge($page->worklogs);
            $latest = $page->latestUpdatedAt?->greaterThan($latest) ? $page->latestUpdatedAt : $latest;
            $offset += GetChangedWorklogsRequest::LIMIT;
        } while ($page->hasNext);

        return new WorklogChanges($worklogs->unique('id')->values(), $latest->getTimestampMs());
    }

    public function deletedSince(int $sinceMilliseconds): DeletedWorklogs
    {
        $since = CarbonImmutable::createFromTimestampMs($sinceMilliseconds);
        $worklogIds = collect();
        $latest = $since;
        $nextKey = null;

        do {
            /** @var TempoDeletedWorklogPage $page */
            $page = $this->connector->send(new GetDeletedWorklogsRequest($since, $nextKey))->dto();
            $worklogIds = $worklogIds->merge($page->worklogIds);
            $latest = $page->latestDeletedAt?->greaterThan($latest) ? $page->latestDeletedAt : $latest;
            $nextKey = $page->nextKey;
        } while ($nextKey !== null);

        return new DeletedWorklogs($worklogIds->unique()->values(), $latest->getTimestampMs());
    }
}
