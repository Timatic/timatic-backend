<?php

namespace Timatic\Rework;

readonly class CompanyId
{
    public function __construct(
        public string $value,
    ) {}

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
