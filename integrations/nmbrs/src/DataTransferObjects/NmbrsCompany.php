<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsCompany
{
    public function __construct(
        public string $companyId,
        public string $name,
    ) {}
}
