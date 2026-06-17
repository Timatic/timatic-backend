<?php

namespace Timatic\Topdesk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskBranch;

class GetBranchesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $fiql) {}

    public function resolveEndpoint(): string
    {
        return '/branches';
    }

    protected function defaultQuery(): array
    {
        return [
            'query' => $this->fiql,
            '$fields' => 'id,name,clientReferenceNumber',
            'page_size' => 1,
            'start' => 0,
        ];
    }

    public function createDtoFromResponse(Response $response): ?TopdeskBranch
    {
        $items = $response->json();

        if (empty($items) || ! isset($items[0]['id'])) {
            return null;
        }

        return new TopdeskBranch(
            id: $items[0]['id'],
            name: $items[0]['name'] ?? '',
            clientReferenceNumber: $items[0]['clientReferenceNumber'] ?? null,
        );
    }
}
