<?php

declare(strict_types=1);

namespace App\Integrations\Git;

use App\DataTransferObjects\GitRemote;
use App\DataTransferObjects\GitRepositoryMapping;

interface GitProviderInterface
{
    /**
     * @return list<string> the hosts whose repositories this provider knows, e.g. bitbucket.org
     */
    public static function hosts(): array;

    /**
     * The customer and budget a repository is mapped to, from Timatic's own records only.
     */
    public function findMappingByRemote(GitRemote $remote): ?GitRepositoryMapping;

    /**
     * Whether a repository, by its identifier in the repositories table, is mapped to a customer.
     */
    public function isMapped(string $repositoryIdentifier): bool;

    /**
     * The admin page where the repository gets its customer, filtered to that repository.
     */
    public function mappingPageUrl(string $repositoryIdentifier): string;

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static;
}
