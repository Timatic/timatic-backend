<?php

namespace Timatic\GitHub\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class GitHubIssue
{
    public function __construct(
        public int $number,
        public string $title,
        public string $htmlUrl,
        public string $state,
        public string $repositoryFullName,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $closedAt,
        public bool $isPullRequest,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromApi(array $data): self
    {
        $closedAt = $data['closed_at'] ?? null;

        return new self(
            number: (int) ($data['number'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            htmlUrl: (string) ($data['html_url'] ?? ''),
            state: (string) ($data['state'] ?? ''),
            repositoryFullName: self::repositoryFullName($data),
            createdAt: CarbonImmutable::parse($data['created_at'] ?? null),
            closedAt: is_string($closedAt) ? CarbonImmutable::parse($closedAt) : null,
            isPullRequest: isset($data['pull_request']),
        );
    }

    public function key(): string
    {
        return $this->repositoryFullName.'#'.$this->number;
    }

    /** @param array<string, mixed> $data */
    private static function repositoryFullName(array $data): string
    {
        $fullName = $data['repository']['full_name'] ?? null;

        if (is_string($fullName)) {
            return $fullName;
        }

        $segments = explode('/', (string) ($data['repository_url'] ?? ''));

        return implode('/', array_slice($segments, -2));
    }
}
