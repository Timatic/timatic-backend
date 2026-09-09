<?php

namespace Timatic\GitHub\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubIssueComment;

class GetIssueCommentsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly int $number,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->owner.'/'.$this->repository.'/issues/'.$this->number.'/comments';
    }

    /** @return array<string, mixed> */
    protected function defaultQuery(): array
    {
        return ['per_page' => 100];
    }

    /** @return array<int, GitHubIssueComment> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $comment) => new GitHubIssueComment(
                id: (int) ($comment['id'] ?? 0),
                authorLogin: (string) ($comment['user']['login'] ?? ''),
                body: trim((string) ($comment['body'] ?? '')),
                createdAt: CarbonImmutable::parse($comment['created_at'] ?? null),
            ),
            $response->json(),
        );
    }
}
