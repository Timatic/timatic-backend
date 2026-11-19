<?php

namespace Timatic\Jira;

use Closure;
use Illuminate\Support\Collection;
use Saloon\Http\Request;
use Timatic\Jira\DataTransferObjects\DeletedWorklogs;
use Timatic\Jira\DataTransferObjects\JiraWorklog;
use Timatic\Jira\DataTransferObjects\JiraWorklogFeedPage;
use Timatic\Jira\DataTransferObjects\WorklogChanges;
use Timatic\Jira\Requests\GetDeletedWorklogIdsRequest;
use Timatic\Jira\Requests\GetUpdatedWorklogIdsRequest;
use Timatic\Jira\Requests\GetWorklogsRequest;

final class JiraWorklogFeed implements WorklogFeed
{
    public function __construct(private readonly Connector $connector) {}

    public function externalSource(): string
    {
        return ServiceProvider::SOURCE_ID;
    }

    public function changedSince(int $sinceMilliseconds): WorklogChanges
    {
        $page = $this->fetchFeed(fn (int $since) => new GetUpdatedWorklogIdsRequest($since), $sinceMilliseconds);

        return new WorklogChanges($this->fetchWorklogs($page->worklogIds), $page->until);
    }

    public function deletedSince(int $sinceMilliseconds): DeletedWorklogs
    {
        $page = $this->fetchFeed(fn (int $since) => new GetDeletedWorklogIdsRequest($since), $sinceMilliseconds);

        return new DeletedWorklogs($page->worklogIds, $page->until);
    }

    /** @param Closure(int): Request $makeRequest */
    private function fetchFeed(Closure $makeRequest, int $sinceMilliseconds): JiraWorklogFeedPage
    {
        $worklogIds = collect();

        do {
            /** @var JiraWorklogFeedPage $page */
            $page = $this->connector->send($makeRequest($sinceMilliseconds))->dto();
            $worklogIds = $worklogIds->merge($page->worklogIds);
            $hasAdvanced = $page->until > $sinceMilliseconds;
            $sinceMilliseconds = $page->until;
        } while (! $page->isLastPage && $hasAdvanced);

        return new JiraWorklogFeedPage($worklogIds->unique()->values(), $page->until, true);
    }

    /**
     * @param  Collection<int, string>  $worklogIds
     * @return Collection<int, JiraWorklog>
     */
    private function fetchWorklogs(Collection $worklogIds): Collection
    {
        return $worklogIds->chunk(1000)->flatMap(function (Collection $chunk): array {
            /** @var array<int, JiraWorklog> $worklogs */
            $worklogs = $this->connector->send(new GetWorklogsRequest($chunk->values()->all()))->dto();

            return $worklogs;
        })->values();
    }
}
