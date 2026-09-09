<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubIssue;

class GetIssueRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $owner,
        private readonly string $repository,
        private readonly int $number,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->owner.'/'.$this->repository.'/issues/'.$this->number;
    }

    public function createDtoFromResponse(Response $response): GitHubIssue
    {
        return GitHubIssue::fromApi($response->json());
    }
}
