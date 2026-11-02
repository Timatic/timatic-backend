<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Where an integration says work in a git repository belongs, as configured in Timatic.
 */
readonly class GitRepositoryMapping
{
    public function __construct(
        public string $repository,
        public int $customer_id,
        public ?int $budget_id,
    ) {}
}
