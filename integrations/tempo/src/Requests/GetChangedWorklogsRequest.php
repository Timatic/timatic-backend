<?php

namespace Timatic\Tempo\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasRequestPagination;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\PaginationPlugin\OffsetPaginator;
use Timatic\Tempo\DataTransferObjects\TempoWorklog;

class GetChangedWorklogsRequest extends Request implements HasRequestPagination, Paginatable
{
    // One page is one queued job, so it has to be done well within the default 60 second worker timeout
    public const LIMIT = 200;

    protected Method $method = Method::GET;

    public function __construct(private readonly CarbonImmutable $updatedFrom) {}

    public function resolveEndpoint(): string
    {
        return '/4/worklogs';
    }

    protected function defaultQuery(): array
    {
        return ['updatedFrom' => $this->updatedFrom->utc()->format('Y-m-d\TH:i:s\Z')];
    }

    /** @return Collection<int, TempoWorklog> */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var array<int, array<string, mixed>> $results */
        $results = $response->json('results') ?? [];

        return collect($results)->map(fn (array $worklog) => new TempoWorklog(
            id: (string) $worklog['tempoWorklogId'],
            issueId: (string) $worklog['issue']['id'],
            authorAccountId: $worklog['author']['accountId'] ?? '',
            startedAt: CarbonImmutable::parse($worklog['startDateTimeUtc'])->utc(),
            updatedAt: CarbonImmutable::parse($worklog['updatedAt'])->utc(),
            timeSpentSeconds: (int) $worklog['timeSpentSeconds'],
            description: trim((string) ($worklog['description'] ?? '')),
        ));
    }

    public function paginate(Connector $connector): OffsetPaginator
    {
        return new class(connector: $connector, request: $this) extends OffsetPaginator
        {
            protected ?int $perPageLimit = GetChangedWorklogsRequest::LIMIT;

            protected function isLastPage(Response $response): bool
            {
                return blank($response->json('metadata.next'));
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->dto()->all();
            }
        };
    }
}
