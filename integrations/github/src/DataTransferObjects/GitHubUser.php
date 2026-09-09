<?php

namespace Timatic\GitHub\DataTransferObjects;

readonly class GitHubUser
{
    public function __construct(
        public int $id,
        public string $login,
    ) {}
}
