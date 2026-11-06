<?php

namespace Timatic\GitHub\DataTransferObjects;

readonly class GitHubRepository
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fullName,
        public string $ownerLogin,
        public bool $isArchived,
    ) {}
}
