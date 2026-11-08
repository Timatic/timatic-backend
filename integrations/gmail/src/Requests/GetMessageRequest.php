<?php

declare(strict_types=1);

namespace Timatic\Gmail\Requests;

use Psr\Http\Message\RequestInterface;
use Saloon\Enums\Method;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Gmail\DataTransferObjects\Message;

class GetMessageRequest extends Request
{
    private const METADATA_HEADERS = ['To', 'Cc', 'Bcc', 'Subject'];

    protected Method $method = Method::GET;

    public function __construct(private readonly string $messageId) {}

    public function resolveEndpoint(): string
    {
        return "/messages/{$this->messageId}";
    }

    /** Gmail reads a list as a repeated parameter, which http_build_query cannot write. */
    public function handlePsrRequest(RequestInterface $request, PendingRequest $pendingRequest): RequestInterface
    {
        $uri = $request->getUri();
        $metadataHeaders = implode('&', array_map(fn (string $header): string => "metadataHeaders={$header}", self::METADATA_HEADERS));

        return $request->withUri($uri->withQuery("{$uri->getQuery()}&{$metadataHeaders}"));
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
