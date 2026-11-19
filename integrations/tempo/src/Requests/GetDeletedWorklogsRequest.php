<?php

namespace Timatic\Tempo\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Tempo\DataTransferObjects\TempoDeletedWorklogPage;

class GetDeletedWorklogsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly CarbonImmutable $updatedFrom, private readonly ?string $lastEvaluatedKey = null) {}

    public function resolveEndpoint(): string
    {
        return '/papertrail/1/events/deleted/types/worklog';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'updatedFrom' => $this->updatedFrom->utc()->format('Y-m-d\TH:i:s\Z'),
            'limit' => 1000,
            'lastEvaluatedKey' => $this->lastEvaluatedKey,
        ]);
    }

    public function createDtoFromResponse(Response $response): TempoDeletedWorklogPage
    {
        /** @var array<int, array{deletedAt: string, tempoWorklogId: string|int}> $results */
        $results = $response->json('results') ?? [];
        $events = collect($results);

        return new TempoDeletedWorklogPage(
            worklogIds: $events->map(fn (array $event) => (string) $event['tempoWorklogId']),
            // Tempo reports deletedAt without a zone, in UTC
            latestDeletedAt: $events->map(fn (array $event) => CarbonImmutable::parse($event['deletedAt'], 'UTC'))->max(),
            nextKey: filled($response->json('metadata.next')) ? $response->json('metadata.lastEvaluatedKey') : null,
        );
    }
}
