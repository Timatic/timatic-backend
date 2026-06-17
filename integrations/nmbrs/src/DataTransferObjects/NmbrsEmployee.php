<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsEmployee
{
    public function __construct(
        public string $employeeId,
        public string $businessEmail,
        public bool $isFulltime,
        public ?string $employeeNumber = null,
    ) {}
}
