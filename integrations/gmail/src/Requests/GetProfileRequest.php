<?php

declare(strict_types=1);

namespace Timatic\Gmail\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetProfileRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/profile';
    }

    /** The current point in the mailbox history, where a sync starts reading from. */
    public function createDtoFromResponse(Response $response): string
    {
        return (string) $response->json('historyId');
    }
}
