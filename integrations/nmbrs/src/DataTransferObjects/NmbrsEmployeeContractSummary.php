<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsEmployeeContractSummary
{
    public function __construct(
        public string $employeeId,
        public bool $isFulltime,
        public bool $isActive,
    ) {}
}
