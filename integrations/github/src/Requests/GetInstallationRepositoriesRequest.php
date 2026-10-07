<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubRepository;
use Timatic\GitHub\DataTransferObjects\GitHubRepositoryPage;

class GetInstallationRepositoriesRequest extends Request
{
    public const PER_PAGE = 100;

    protected Method $method = Method::GET;

    public function __construct(private readonly int $page = 1) {}

    public function resolveEndpoint(): string
    {
        return '/installation/repositories';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return [
            'per_page' => self::PER_PAGE,
            'page' => $this->page,
        ];
    }

    public function createDtoFromResponse(Response $response): GitHubRepositoryPage
    {
        return new GitHubRepositoryPage(
            repositories: array_map(
                fn (array $repository) => new GitHubRepository(
                    id: (int) ($repository['id'] ?? 0),
                    name: (string) ($repository['name'] ?? ''),
                    fullName: (string) ($repository['full_name'] ?? ''),
                    ownerLogin: (string) ($repository['owner']['login'] ?? ''),
                    isArchived: (bool) ($repository['archived'] ?? false),
                ),
                $response->json('repositories') ?? [],
            ),
            totalCount: (int) $response->json('total_count'),
        );
    }
}
