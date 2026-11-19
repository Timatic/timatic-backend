<?php

namespace Timatic\Tempo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Tempo\DataTransferObjects\TempoWorklogReference;

class GetWorklogByJiraIdRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $jiraWorklogId) {}

    public function resolveEndpoint(): string
    {
        return '/4/worklogs/jira/'.$this->jiraWorklogId;
    }

    public function createDtoFromResponse(Response $response): TempoWorklogReference
    {
        return new TempoWorklogReference((string) $response->json('tempoWorklogId'));
    }
}
