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
use Saloon\PaginationPlugin\CursorPaginator;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklog;

class GetDeletedWorklogsRequest extends Request implements HasRequestPagination, Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(private readonly CarbonImmutable $updatedFrom) {}

    public function resolveEndpoint(): string
    {
        return '/papertrail/1/events/deleted/types/worklog';
    }

    protected function defaultQuery(): array
    {
        return [
            'updatedFrom' => $this->updatedFrom->utc()->format('Y-m-d\TH:i:s\Z'),
            'limit' => 1000,
        ];
    }

    /** @return Collection<int, TempoDeletedWorklog> */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var array<int, array{deletedAt: string, tempoWorklogId: string|int}> $results */
        $results = $response->json('results') ?? [];

        return collect($results)->map(fn (array $event) => new TempoDeletedWorklog(
            id: (string) $event['tempoWorklogId'],
            // Tempo reports deletedAt without a zone, in UTC
            deletedAt: CarbonImmutable::parse($event['deletedAt'], 'UTC'),
        ));
    }

    public function paginate(Connector $connector): CursorPaginator
    {
        return new class(connector: $connector, request: $this) extends CursorPaginator
        {
            protected function applyPagination(Request $request): Request
            {
                if ($this->currentResponse instanceof Response) {
                    $request->query()->add('lastEvaluatedKey', $this->getNextCursor($this->currentResponse));
                }

                return $request;
            }

            protected function getNextCursor(Response $response): string
            {
                return (string) $response->json('metadata.lastEvaluatedKey');
            }

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
