<?php

namespace Timatic\Jira\Requests;

use Carbon\CarbonImmutable;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Timatic\Jira\DataTransferObjects\JiraWorklog;

class GetWorklogsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<int, string> $worklogIds */
    public function __construct(private readonly array $worklogIds) {}

    // API v2 returns the comment as plain text, v3 would return ADF
    public function resolveEndpoint(): string
    {
        return '/rest/api/2/worklog/list';
    }

    /** @return array<string, array<int, int>> */
    protected function defaultBody(): array
    {
        return ['ids' => array_map('intval', $this->worklogIds)];
    }

    /** @return array<int, JiraWorklog> */
    public function createDtoFromResponse(Response $response): array
    {
        /** @var array<int, array<string, mixed>> $worklogs */
        $worklogs = $response->json();

        return array_map(fn (array $worklog) => new JiraWorklog(
            id: (string) $worklog['id'],
            issueId: (string) $worklog['issueId'],
            authorAccountId: $worklog['author']['accountId'] ?? '',
            startedAt: CarbonImmutable::parse($worklog['started'])->utc(),
            timeSpentSeconds: (int) $worklog['timeSpentSeconds'],
            comment: trim((string) ($worklog['comment'] ?? '')),
        ), $worklogs);
    }
}
