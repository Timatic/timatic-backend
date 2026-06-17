<?php

namespace Timatic\Nmbrs\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Nmbrs\DataTransferObjects\NmbrsHourCode;

class GetHourCodesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $companyId) {}

    public function resolveEndpoint(): string
    {
        return '/api/companies/'.$this->companyId.'/hourcodes';
    }

    protected function defaultQuery(): array
    {
        return ['pageSize' => 100];
    }

    /** @return Collection<int, NmbrsHourCode> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['code'], $item['description']))
            ->map(fn (array $item): NmbrsHourCode => new NmbrsHourCode(
                code: (int) $item['code'],
                description: (string) $item['description'],
            ))
            ->values();
    }
}
