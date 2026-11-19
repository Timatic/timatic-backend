<?php

namespace Timatic\Tempo\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteWorklogRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $tempoWorklogId) {}

    public function resolveEndpoint(): string
    {
        return '/4/worklogs/'.$this->tempoWorklogId;
    }
}
