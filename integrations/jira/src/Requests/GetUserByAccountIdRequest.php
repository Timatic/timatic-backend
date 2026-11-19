<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraUser;

class GetUserByAccountIdRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $accountId) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/user';
    }

    protected function defaultQuery(): array
    {
        return ['accountId' => $this->accountId];
    }

    public function createDtoFromResponse(Response $response): JiraUser
    {
        return new JiraUser(
            accountId: $response->json('accountId') ?? '',
            emailAddress: $response->json('emailAddress') ?? '',
        );
    }
}
