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

class GetWorklogsRequest extends Request implements HasRequestPagination, Paginatable
{
    public const LIMIT = 200;

    protected Method $method = Method::GET;

    /** @param array<string, string> $filter */
    private function __construct(private readonly array $filter) {}

    public static function changedSince(CarbonImmutable $updatedFrom): self
    {
        return new self(['updatedFrom' => $updatedFrom->utc()->format('Y-m-d\TH:i:s\Z')]);
    }

    /** The worklogs of the days $from through $to */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): self
    {
        return new self(['from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    public function resolveEndpoint(): string
    {
        return '/4/worklogs';
    }

    protected function defaultQuery(): array
    {
        return $this->filter;
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
            protected ?int $perPageLimit = GetWorklogsRequest::LIMIT;

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
