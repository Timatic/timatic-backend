<?php

namespace Timatic\Nmbrs\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployeePersonalInfo;

class GetPersonalInfoRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $companyId) {}

    public function resolveEndpoint(): string
    {
        return '/api/companies/'.$this->companyId.'/employees/personalinfo';
    }

    protected function defaultQuery(): array
    {
        return ['pageSize' => 100];
    }

    /** @return Collection<int, NmbrsEmployeePersonalInfo> */
    public function createDtoFromResponse(Response $response): Collection
    {
        $data = $response->json('data');

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['employeeId']))
            ->map(function (array $item): NmbrsEmployeePersonalInfo {
                $infoList = is_array($item['info'] ?? null) ? $item['info'] : [];
                $latestInfo = collect($infoList)
                    ->filter(fn (mixed $info): bool => is_array($info))
                    ->sortByDesc(fn (array $info): int => ($info['period']['year'] ?? 0) * 100 + ($info['period']['period'] ?? 0))
                    ->first();

                $email = is_array($latestInfo) ? ($latestInfo['contactInfo']['businessEmail'] ?? null) : null;
                $basicInfo = is_array($latestInfo) && is_array($latestInfo['basicInfo'] ?? null) ? $latestInfo['basicInfo'] : [];
                $employeeNumber = $basicInfo['employeeNumber'] ?? null;

                return new NmbrsEmployeePersonalInfo(
                    employeeId: (string) $item['employeeId'],
                    businessEmail: is_string($email) ? $email : null,
                    employeeNumber: $employeeNumber !== null ? (string) $employeeNumber : null,
                );
            })
            ->values();
    }
}
