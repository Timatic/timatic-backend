<?php

declare(strict_types=1);

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraUser;

class GetMyselfRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/myself';
    }

    public function createDtoFromResponse(Response $response): JiraUser
    {
        return new JiraUser(
            accountId: $response->json('accountId') ?? '',
            emailAddress: $response->json('emailAddress') ?? '',
            displayName: $response->json('displayName') ?? '',
        );
    }
}
