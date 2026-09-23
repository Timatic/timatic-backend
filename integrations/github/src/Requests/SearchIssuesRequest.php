<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubIssue;

class SearchIssuesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $searchQuery) {}

    public function resolveEndpoint(): string
    {
        return '/search/issues';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'q' => $this->searchQuery,
            'per_page' => 50,
            'advanced_search' => 'true',
        ];
    }

    /** @return array<int, GitHubIssue> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $issue) => GitHubIssue::fromApi($issue),
            $response->json('items') ?? [],
        );
    }
}
