<?php

namespace Timatic\Rework\DataTransferObjects;

readonly class ReworkUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
