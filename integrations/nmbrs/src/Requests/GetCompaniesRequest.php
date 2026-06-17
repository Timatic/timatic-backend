<?php

namespace Timatic\Nmbrs\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Nmbrs\DataTransferObjects\NmbrsCompany;

class GetCompaniesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/companies';
    }

    /** @return Collection<int, NmbrsCompany> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['companyId'], $item['name']))
            ->map(fn (array $item): NmbrsCompany => new NmbrsCompany(
                companyId: (string) $item['companyId'],
                name: (string) $item['name'],
            ))
            ->values();
    }
}
