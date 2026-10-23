<?php

namespace Timatic\GitHub;

final readonly class GitHubCredentials
{
    public function __construct(public string $token) {}
}
