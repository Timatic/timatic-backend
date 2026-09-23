<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\GitHub\DataTransferObjects\GitHubUser;

class GetAuthenticatedUserRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/user';
    }

    public function createDtoFromResponse(Response $response): GitHubUser
    {
        return new GitHubUser(
            id: (int) $response->json('id'),
            login: (string) $response->json('login'),
        );
    }
}
