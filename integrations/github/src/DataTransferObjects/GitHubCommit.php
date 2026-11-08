<?php

namespace Timatic\GitHub\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class GitHubCommit
{
    public function __construct(
        public string $sha,
        public ?string $authorEmail,
        public ?string $authorLogin,
        public CarbonImmutable $committedAt,
    ) {}
}
