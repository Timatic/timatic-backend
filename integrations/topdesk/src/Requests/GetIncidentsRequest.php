<?php

namespace Timatic\Topdesk\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskIncident;

class GetIncidentsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $fiql) {}

    public function resolveEndpoint(): string
    {
        return '/incidents';
    }

    protected function defaultQuery(): array
    {
        return [
            'query' => $this->fiql,
            '$fields' => 'id,number,briefDescription,creationDate,closedDate,callerBranch',
            'page_size' => 50,
            'start' => 0,
        ];
    }

    /** @return array<int, TopdeskIncident> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(function (array $incident): TopdeskIncident {
            return new TopdeskIncident(
                id: $incident['id'],
                number: $incident['number'],
                briefDescription: $incident['briefDescription'] ?? '',
                creationDate: CarbonImmutable::parse($incident['creationDate']),
                closedDate: isset($incident['closedDate']) ? CarbonImmutable::parse($incident['closedDate']) : null,
                callerBranchId: $incident['callerBranch']['id'] ?? null,
                callerBranchClientReferenceNumber: $incident['callerBranch']['clientReferenceNumber'] ?? null,
            );
        }, $response->json());
    }
}
