<?php

namespace Timatic\Jira\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasRequestPagination;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\PaginationPlugin\CursorPaginator;
use Timatic\Jira\DataTransferObjects\JiraIssue;

class GetIssuesRequest extends Request implements HasRequestPagination, Paginatable
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
            'fields' => 'summary,created,resolutiondate,issuetype,parent',
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
                id: isset($issue['id']) ? (string) $issue['id'] : null,
                parentKey: $fields['parent']['key'] ?? null,
                hierarchyLevel: (int) ($fields['issuetype']['hierarchyLevel'] ?? 0),
            );
        }, $response->json('issues') ?? []);
    }

    public function paginate(Connector $connector): CursorPaginator
    {
        return new class(connector: $connector, request: $this) extends CursorPaginator
        {
            protected function applyPagination(Request $request): Request
            {
                if ($this->currentResponse instanceof Response) {
                    $request->query()->add('nextPageToken', $this->getNextCursor($this->currentResponse));
                }

                return $request;
            }

            protected function getNextCursor(Response $response): string
            {
                return (string) $response->json('nextPageToken');
            }

            protected function isLastPage(Response $response): bool
            {
                return blank($response->json('nextPageToken'));
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->dto();
            }
        };
    }
}
