<?php

namespace Timatic\Nmbrs;

use Saloon\Http\Connector as SaloonConnector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;

class Connector extends SaloonConnector implements HasPagination
{
    public function __construct(private readonly string $accessToken) {}

    public function resolveBaseUrl(): string
    {
        return 'https://api.nmbrsapp.com';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->accessToken,
            'X-Subscription-Key' => config('nmbrs.subscription_key'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function paginate(Request $request): PagedPaginator
    {
        return new class(connector: $this, request: $request) extends PagedPaginator
        {
            protected function isLastPage(Response $response): bool
            {
                return $response->json('pagination.nextPage') === null;
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->dto()->all();
            }

            protected function applyPagination(Request $request): Request
            {
                $request->query()->add('pageNumber', $this->currentPage + 1);

                return $request;
            }
        };
    }
}
