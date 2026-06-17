<?php

namespace Timatic\Jira\DataTransferObjects;

readonly class JiraUser
{
    public function __construct(
        public string $accountId,
        public string $emailAddress,
    ) {}
}
