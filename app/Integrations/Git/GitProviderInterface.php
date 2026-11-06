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

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static;
}
