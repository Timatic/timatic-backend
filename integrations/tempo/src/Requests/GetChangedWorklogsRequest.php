<?php

namespace Timatic\Tempo\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraWorklog;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPage;

class GetChangedWorklogsRequest extends Request
{
    public const LIMIT = 1000;

    protected Method $method = Method::GET;

    public function __construct(private readonly CarbonImmutable $updatedFrom, private readonly int $offset = 0) {}

    public function resolveEndpoint(): string
    {
        return '/4/worklogs';
    }

    protected function defaultQuery(): array
    {
        return [
            'updatedFrom' => $this->updatedFrom->utc()->format('Y-m-d\TH:i:s\Z'),
            'offset' => $this->offset,
            'limit' => self::LIMIT,
        ];
    }

    public function createDtoFromResponse(Response $response): TempoWorklogPage
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];
        $worklogs = collect($results);

        return new TempoWorklogPage(
            worklogs: $worklogs->map(fn (array $worklog) => new JiraWorklog(
                id: (string) $worklog['tempoWorklogId'],
                issueId: (string) $worklog['issue']['id'],
                authorAccountId: $worklog['author']['accountId'] ?? '',
                startedAt: CarbonImmutable::parse($worklog['startDateTimeUtc'])->utc(),
                timeSpentSeconds: (int) $worklog['timeSpentSeconds'],
                comment: trim((string) ($worklog['description'] ?? '')),
            )),
            latestUpdatedAt: $worklogs->map(fn (array $worklog) => CarbonImmutable::parse($worklog['updatedAt'])->utc())->max(),
            hasNext: filled($response->json('metadata.next')),
        );
    }
}
