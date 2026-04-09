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
        return '/user/workspaces';
    }

    /** @return array<int, BitbucketWorkspace> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $permission) => new BitbucketWorkspace(
                slug: $permission['workspace']['slug'] ?? '',
                isAdministrator: $permission['administrator'] ?? false,
            ),
            $response->json('values') ?? [],
        );
    }
}
