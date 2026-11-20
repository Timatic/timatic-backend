<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraUser;

class GetUserByEmailRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $email) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/user/search';
    }

    protected function defaultQuery(): array
    {
        return ['query' => $this->email, 'maxResults' => 1];
    }

    public function createDtoFromResponse(Response $response): ?JiraUser
    {
        $user = $response->json('0');

        return blank($user['accountId'] ?? null) ? null : new JiraUser(
            accountId: $user['accountId'],
            emailAddress: $user['emailAddress'] ?? $this->email,
        );
    }
}
