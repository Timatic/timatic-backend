<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsHourCode
{
    public function __construct(
        public int $code,
        public string $description,
    ) {}

    public function label(): string
    {
        return $this->code.' - '.$this->description;
    }
}
