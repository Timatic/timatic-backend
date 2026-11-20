<?php

namespace Timatic\GitHub\Requests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubCommit;

/**
 * Commits on the default branch, newest first.
 */
class GetRepositoryCommitsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $repositoryFullName,
        private readonly CarbonInterface $since,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repositoryFullName.'/commits';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['since' => $this->since->toIso8601ZuluString(), 'per_page' => 100];
    }

    /** @return array<int, GitHubCommit> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $commit) => new GitHubCommit(
                sha: (string) ($commit['sha'] ?? ''),
                authorEmail: $commit['commit']['author']['email'] ?? null,
                // null when GitHub cannot link the commit to an account
                authorLogin: $commit['author']['login'] ?? null,
                committedAt: CarbonImmutable::parse($commit['commit']['committer']['date'] ?? null),
            ),
            $response->json(),
        );
    }
}
