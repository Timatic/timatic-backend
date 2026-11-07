<?php

namespace Timatic\GitHub\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class DeleteRepositoryWebhookRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $repositoryFullName,
        private readonly int $webhookId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repositoryFullName.'/hooks/'.$this->webhookId;
    }

    /** The response carries no body worth mapping. */
    public function createDtoFromResponse(Response $response): null
    {
        return null;
    }
}
