<?php

namespace Timatic\Bitbucket\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWorkspace;

class GetWorkspacesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/workspaces';
    }

    protected function defaultQuery(): array
    {
        return [
            'pagelen' => 100,
            'fields' => 'values.slug,values.name',
            'sort' => 'name',
        ];
    }

    /** @return array<int, BitbucketWorkspace> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $workspace) => new BitbucketWorkspace(
                slug: $workspace['slug'] ?? '',
                name: $workspace['name'] ?? '',
            ),
            $response->json('values') ?? [],
        );
    }
}
