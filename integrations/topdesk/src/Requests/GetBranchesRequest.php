<?php

namespace Timatic\Topdesk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskBranch;

class GetBranchesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $fiql,
        private readonly string $matchField,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/branches';
    }

    protected function defaultQuery(): array
    {
        return [
            'query' => $this->fiql,
            '$fields' => "id,{$this->matchField}",
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
            matchValue: $items[0][$this->matchField] ?? null,
        );
    }
}
