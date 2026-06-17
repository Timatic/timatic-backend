<?php

namespace Timatic\Nmbrs\Services;

use Illuminate\Support\Collection;
use Timatic\Nmbrs\Connector;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployee;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployeeContractSummary;
use Timatic\Nmbrs\DataTransferObjects\NmbrsEmployeePersonalInfo;
use Timatic\Nmbrs\Requests\GetContractsRequest;
use Timatic\Nmbrs\Requests\GetPersonalInfoRequest;

readonly class NmbrsEmployeeService
{
    public function __construct(
        private Connector $connector,
        private string $companyId,
    ) {}

    /**
     * Returns active employees indexed by lowercase business email.
     *
     * @return Collection<string, NmbrsEmployee>
     */
    public function listByEmail(): Collection
    {
        $personalInfoMap = $this->fetchPersonalInfoByEmployeeId();
        $contractMap = $this->fetchContractSummaryByEmployeeId();

        return $personalInfoMap
            ->filter(fn (NmbrsEmployeePersonalInfo $info): bool => filled($info->businessEmail) && ($contractMap->get($info->employeeId)->isActive ?? false)
            )
            ->map(function (NmbrsEmployeePersonalInfo $info) use ($contractMap): NmbrsEmployee {
                $contract = $contractMap->get($info->employeeId);

                return new NmbrsEmployee(
                    employeeId: $info->employeeId,
                    businessEmail: strtolower((string) $info->businessEmail),
                    isFulltime: $contract->isFulltime ?? false,
                    employeeNumber: $info->employeeNumber,
                );
            })
            ->keyBy(fn (NmbrsEmployee $employee) => $employee->businessEmail);
    }

    /**
     * @return Collection<string, NmbrsEmployeePersonalInfo> keyed by employeeId
     */
    private function fetchPersonalInfoByEmployeeId(): Collection
    {
        return $this->connector->paginate(new GetPersonalInfoRequest($this->companyId))
            ->collect() // LazyCollection of DTOs via getPageItems
            ->collect() // eager Collection
            ->keyBy(fn (NmbrsEmployeePersonalInfo $info) => $info->employeeId);
    }

    /**
     * @return Collection<string, NmbrsEmployeeContractSummary> keyed by employeeId
     */
    private function fetchContractSummaryByEmployeeId(): Collection
    {
        return $this->connector->paginate(new GetContractsRequest($this->companyId))
            ->collect() // LazyCollection of DTOs via getPageItems
            ->collect() // eager Collection
            ->keyBy(fn (NmbrsEmployeeContractSummary $summary) => $summary->employeeId);
    }
}
