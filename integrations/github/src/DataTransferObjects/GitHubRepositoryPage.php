<?php

namespace Timatic\GitHub\DataTransferObjects;

readonly class GitHubRepositoryPage
{
    /** @param array<int, GitHubRepository> $repositories */
    public function __construct(
        public array $repositories,
        public int $totalCount,
    ) {}
}
