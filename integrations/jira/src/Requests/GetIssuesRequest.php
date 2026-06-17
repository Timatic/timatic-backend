<?php

namespace Timatic\Jira\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraIssue;

class GetIssuesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $jql) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/search/jql';
    }

    protected function defaultQuery(): array
    {
        return [
            'jql' => $this->jql,
            'maxResults' => 50,
            'fields' => 'summary,created,resolutiondate,issuetype',
        ];
    }

    /** @return array<int, JiraIssue> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(function (array $issue): JiraIssue {
            $fields = $issue['fields'] ?? [];

            return new JiraIssue(
                key: $issue['key'],
                issueType: $fields['issuetype']['name'] ?? '',
                summary: $fields['summary'] ?? '',
                createdAt: CarbonImmutable::parse($fields['created']),
                resolvedAt: isset($fields['resolutiondate']) ? CarbonImmutable::parse($fields['resolutiondate']) : null,
            );
        }, $response->json('issues') ?? []);
    }
}
