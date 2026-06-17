<?php

namespace Timatic\Topdesk\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskIncident;

class GetIncidentByNumberRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $number) {}

    public function resolveEndpoint(): string
    {
        return '/incidents/number/'.rawurlencode($this->number);
    }

    public function createDtoFromResponse(Response $response): ?TopdeskIncident
    {
        $data = $response->json();

        if (empty($data) || ! isset($data['id'])) {
            return null;
        }

        return new TopdeskIncident(
            id: $data['id'],
            number: $data['number'],
            briefDescription: $data['briefDescription'] ?? '',
            creationDate: CarbonImmutable::parse($data['creationDate']),
            closedDate: isset($data['closedDate']) ? CarbonImmutable::parse($data['closedDate']) : null,
            callerBranchId: $data['callerBranch']['id'] ?? null,
            callerBranchClientReferenceNumber: $data['callerBranch']['clientReferenceNumber'] ?? null,
        );
    }
}
