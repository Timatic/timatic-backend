<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsEmployeePersonalInfo
{
    public function __construct(
        public string $employeeId,
        public ?string $businessEmail,
        public ?string $employeeNumber,
    ) {}
}
