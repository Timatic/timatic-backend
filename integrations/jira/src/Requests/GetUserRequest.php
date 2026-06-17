<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraUser;

class GetUserRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $email) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/user/search';
    }

    protected function defaultQuery(): array
    {
        return ['query' => $this->email, 'maxResults' => 10];
    }

    /** @return array<int, JiraUser> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $user) => new JiraUser(
                accountId: $user['accountId'] ?? '',
                emailAddress: $user['emailAddress'] ?? '',
            ),
            $response->json(),
        );
    }
}
