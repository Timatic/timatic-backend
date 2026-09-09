<?php

namespace Timatic\GitHub\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class GitHubIssueComment
{
    public function __construct(
        public int $id,
        public string $authorLogin,
        public string $body,
        public CarbonImmutable $createdAt,
    ) {}
}
