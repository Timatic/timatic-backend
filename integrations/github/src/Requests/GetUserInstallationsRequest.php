<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubInstallation;

class GetUserInstallationsRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/user/installations';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['per_page' => 100];
    }

    /** @return array<int, GitHubInstallation> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $installation) => new GitHubInstallation(
                id: (int) ($installation['id'] ?? 0),
                accountLogin: (string) ($installation['account']['login'] ?? ''),
                accountType: (string) ($installation['account']['type'] ?? ''),
            ),
            $response->json('installations') ?? [],
        );
    }
}
