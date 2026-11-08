<?php

declare(strict_types=1);

namespace Timatic\Gmail\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Gmail\DataTransferObjects\HistoryPage;

/**
 * The mailbox changes since a point in its history. The gmail.metadata scope refuses a search
 * query, so reading on from the last seen history id is how new mail is found.
 */
class ListHistoryRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $startHistoryId,
        private readonly ?string $pageToken = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/history';
    }

    public function createDtoFromResponse(Response $response): HistoryPage
    {
        return HistoryPage::fromApiResponse($response->json());
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'startHistoryId' => $this->startHistoryId,
            'pageToken' => $this->pageToken,
            'maxResults' => 500,
        ]);
    }
}
