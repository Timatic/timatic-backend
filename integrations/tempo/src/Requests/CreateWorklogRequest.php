<?php

namespace Timatic\Tempo\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPayload;
use Timatic\Tempo\DataTransferObjects\TempoWorklogReference;

class CreateWorklogRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly TempoWorklogPayload $payload) {}

    public function resolveEndpoint(): string
    {
        return '/4/worklogs';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->payload->toArray();
    }

    public function createDtoFromResponse(Response $response): TempoWorklogReference
    {
        return new TempoWorklogReference((string) $response->json('tempoWorklogId'));
    }
}
