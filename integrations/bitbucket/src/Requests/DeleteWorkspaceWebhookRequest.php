<?php

namespace Timatic\Bitbucket\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class DeleteWorkspaceWebhookRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $workspace,
        private readonly string $webhookUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/workspaces/'.$this->workspace.'/hooks/'.$this->webhookUuid;
    }

    /** The response carries no body worth mapping. */
    public function createDtoFromResponse(Response $response): null
    {
        return null;
    }
}
