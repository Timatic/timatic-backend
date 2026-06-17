<?php

namespace Timatic\Rework\DataTransferObjects;

class ReworkUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
