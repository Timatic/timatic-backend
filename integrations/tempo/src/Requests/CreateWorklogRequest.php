<?php

namespace Timatic\Tempo\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Timatic\Tempo\DataTransferObjects\TempoWorklogPayload;

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
}
