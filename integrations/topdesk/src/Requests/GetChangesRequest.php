<?php

namespace Timatic\Topdesk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskChange;

class GetChangesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $fiql) {}

    public function resolveEndpoint(): string
    {
        return '/operatorChanges';
    }

    protected function defaultQuery(): array
    {
        return [
            'query' => $this->fiql,
            'fields' => 'id,number,briefDescription,creationDate,simple.closedDate,branch',
            'sort' => 'creationDate:desc',
            'pageSize' => 50,
            'pageStart' => 0,
        ];
    }

    /** @return array<int, TopdeskChange> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $change): TopdeskChange => TopdeskChange::fromArray($change),
            $response->json('results') ?? [],
        );
    }
}
