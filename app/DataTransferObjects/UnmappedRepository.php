<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * A repository with recent work but no customer, as listed in the admins' digest.
 */
readonly class UnmappedRepository
{
    public function __construct(
        public string $integrationTitle,
        public string $repositoryName,
        public string $mappingPageUrl,
    ) {}
}
