<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Exception;

class DerivedPermission
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(
        public readonly string $name,
        public readonly array $values = [],
    ) {}

    /**
     * @throws Exception
     */
    public function getAttribute(string $name): string
    {
        return $this->$name ?? throw new Exception('Attribute does not exist');
    }
}
