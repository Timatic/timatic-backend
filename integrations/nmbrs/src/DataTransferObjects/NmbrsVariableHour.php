<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class NmbrsVariableHour
{
    public function __construct(
        public int $hourCode,
        public float $hours,
    ) {}
}
