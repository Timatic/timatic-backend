<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubIssue;

class GetIssuesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly string $state = 'open',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->owner.'/'.$this->repository.'/issues';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'state' => $this->state,
            'sort' => 'updated',
            'per_page' => 100,
        ];
    }

    /** @return array<int, GitHubIssue> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $issue) => GitHubIssue::fromApi($issue),
            $response->json(),
        );
    }
}
