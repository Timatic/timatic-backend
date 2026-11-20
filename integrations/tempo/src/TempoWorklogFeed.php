<?php

namespace Timatic\Tempo;

use Carbon\CarbonImmutable;
use Timatic\Tempo\DataTransferObjects\DeletedWorklogs;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklogPage;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPage;
use Timatic\Tempo\Requests\GetChangedWorklogsRequest;
use Timatic\Tempo\Requests\GetDeletedWorklogsRequest;

/**
 * Reads worklogs from Tempo, which keeps the real author and description
 * that Tempo strips from its Jira copies.
 */
final class TempoWorklogFeed
{
    public function __construct(private readonly Connector $connector) {}

    public function changedPage(int $sinceMilliseconds, int $offset): TempoWorklogPage
    {
        return $this->connector->send(new GetChangedWorklogsRequest(CarbonImmutable::createFromTimestampMs($sinceMilliseconds), $offset))->dto();
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
