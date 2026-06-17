<?php

namespace Timatic\Jira\DataTransferObjects;

readonly class JiraProject
{
    public function __construct(
        public string $key,
        public string $name,
    ) {}
}
