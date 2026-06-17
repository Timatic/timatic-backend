<?php

namespace Timatic\Nmbrs\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Nmbrs\DataTransferObjects\NmbrsVariableHour;

class GetVariableHoursRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $employeeId
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/employees/'.$this->employeeId.'/variablehours';
    }

    protected function defaultQuery(): array
    {
        return [];
    }

    /** @return Collection<int, NmbrsVariableHour> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['hourCode']))
            ->map(fn (array $item): NmbrsVariableHour => new NmbrsVariableHour(
                hourCode: (int) $item['hourCode'],
                hours: (float) ($item['hours'] ?? 0),
            ))
            ->values();
    }
}
