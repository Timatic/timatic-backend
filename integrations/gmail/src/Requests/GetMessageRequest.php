<?php

declare(strict_types=1);

namespace Timatic\Gmail\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Gmail\DataTransferObjects\Message;

class GetMessageRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $messageId) {}

    public function resolveEndpoint(): string
    {
        return "/messages/{$this->messageId}";
    }

    public function createDtoFromResponse(Response $response): Message
    {
        return Message::fromApiResponse($response->json());
    }

    protected function defaultQuery(): array
    {
        return ['format' => 'metadata'];
    }
}
