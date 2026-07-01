<?php

namespace Timatic\Rework;

use Illuminate\Support\Collection;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Connector as SaloonConnector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;

class Connector extends SaloonConnector implements HasPagination
{
    public function __construct(
        private readonly ApiKey $apiKey,
        private readonly CompanyId $companyId,
    ) {}

    public function resolveBaseUrl(): string
    {
        return 'https://api.rework.nl/v2/'.$this->companyId;
    }

    protected function defaultAuth(): HeaderAuthenticator
    {
        return new HeaderAuthenticator((string) $this->apiKey, 'Authorization', 'Token ');
    }

    protected function defaultHeaders(): array
    {
        return [
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
                $dto = $response->dto();

                if (! $dto instanceof Collection) {
                    return true;
                }

                return $dto->count() < 100;
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                $dto = $response->dto();

                if (! $dto instanceof Collection) {
                    return [];
                }

                return $dto->all();
            }

            protected function applyPagination(Request $request): Request
            {
                $request->query()->add('page', $this->currentPage + 1);
                $request->query()->add('per_page', 100);

                return $request;
            }
        };
    }
}
