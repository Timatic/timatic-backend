<?php

namespace Timatic\Nmbrs\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployeeContractSummary;

class GetContractsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $companyId) {}

    public function resolveEndpoint(): string
    {
        return '/api/companies/'.$this->companyId.'/employees/contracts';
    }

    protected function defaultQuery(): array
    {
        return ['pageSize' => 100];
    }

    /** @return Collection<int, NmbrsEmployeeContractSummary> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['employeeId']))
            ->map(function (array $item): NmbrsEmployeeContractSummary {
                $contracts = is_array($item['contracts'] ?? null) ? $item['contracts'] : [];
                $activeContract = collect($contracts)
                    ->filter(fn (mixed $contract): bool => is_array($contract))
                    ->filter(fn (array $contract): bool => empty($contract['endDate']) || strtotime($contract['endDate']) >= now()->timestamp)
                    ->sortByDesc('startDate')
                    ->first();

                $hoursPerWeek = is_array($activeContract) ? (float) ($activeContract['hoursPerWeek'] ?? 0) : 0.0;

                return new NmbrsEmployeeContractSummary(
                    employeeId: (string) $item['employeeId'],
                    isFulltime: $hoursPerWeek >= 40,
                    isActive: $activeContract !== null,
                );
            })
            ->values();
    }
}
