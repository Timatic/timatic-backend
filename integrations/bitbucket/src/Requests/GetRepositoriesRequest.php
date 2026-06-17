<?php

namespace Timatic\Bitbucket\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Bitbucket\DataTransferObjects\BitbucketRepository;

class GetRepositoriesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $workspace) {}

    public function resolveEndpoint(): string
    {
        return '/repositories/'.$this->workspace;
    }

    protected function defaultQuery(): array
    {
        return [
            'pagelen' => 100,
            'fields' => 'values.slug,values.full_name,values.name,next',
            'sort' => 'name',
        ];
    }

    /** @return array<int, BitbucketRepository> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $repo) => new BitbucketRepository(
                slug: $repo['slug'] ?? '',
                fullName: $repo['full_name'] ?? '',
                name: $repo['name'] ?? '',
            ),
            $response->json('values') ?? [],
        );
    }
}
