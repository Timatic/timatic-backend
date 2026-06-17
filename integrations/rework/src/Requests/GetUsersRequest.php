<?php

namespace Timatic\Rework\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Timatic\Rework\DataTransferObjects\ReworkUser;

class GetUsersRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/leave/users';
    }

    /** @return Collection<int, ReworkUser> */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var array<mixed> $items */
        $items = $response->json();

        return collect($items)
            ->map(fn (mixed $item): ReworkUser => new ReworkUser(
                id: (int) $item['id'],
                name: (string) ($item['name'] ?? ''),
                email: strtolower((string) $item['email']),
            ))
            ->values();
    }
}
