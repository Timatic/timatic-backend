<?php

namespace Timatic\Nmbrs\DataTransferObjects;

class NmbrsHourCode
{
    public function __construct(
        public readonly int $code,
        public readonly string $description,
    ) {}

    public function label(): string
    {
        return $this->code.' - '.$this->description;
    }
}
