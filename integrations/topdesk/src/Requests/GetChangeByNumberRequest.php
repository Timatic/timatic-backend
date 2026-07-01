<?php

namespace Timatic\Topdesk\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskChange;

class GetChangeByNumberRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $number) {}

    public function resolveEndpoint(): string
    {
        return '/operatorChanges/'.rawurlencode($this->number);
    }

    public function createDtoFromResponse(Response $response): ?TopdeskChange
    {
        $data = $response->json();

        if (empty($data) || ! isset($data['id'])) {
            return null;
        }

        return TopdeskChange::fromArray($data);
    }
}
