<?php

namespace Timatic\GitHub\DataTransferObjects;

readonly class GitHubInstallation
{
    public function __construct(
        public int $id,
        public string $accountLogin,
        public string $accountType,
    ) {}
}
