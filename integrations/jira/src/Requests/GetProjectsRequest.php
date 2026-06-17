<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraProject;

class GetProjectsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/project/search';
    }

    protected function defaultQuery(): array
    {
        return [
            'maxResults' => 50,
            'orderBy' => 'name',
        ];
    }

    /** @return array<int, JiraProject> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $project) => new JiraProject(
                key: $project['key'] ?? '',
                name: $project['name'] ?? '',
            ),
            $response->json('values') ?? [],
        );
    }
}
