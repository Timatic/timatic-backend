<?php

namespace Timatic\Jira\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraChangelogEntry;
use Timatic\Jira\DataTransferObjects\JiraComment;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\DataTransferObjects\JiraIssueDetails;

class GetIssueRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $key) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/issue/'.$this->key;
    }

    protected function defaultQuery(): array
    {
        return [
            'fields' => 'summary,created,resolutiondate,issuetype,comment',
            'expand' => 'renderedFields,changelog',
        ];
    }

    public function createDtoFromResponse(Response $response): JiraIssueDetails
    {
        $data = $response->json();
        $fields = $data['fields'] ?? [];
        /** @var array<int, array<string, mixed>> $comments */
        $comments = $fields['comment']['comments'] ?? [];
        /** @var array<int, array<string, mixed>> $renderedComments */
        $renderedComments = $data['renderedFields']['comment']['comments'] ?? [];
        /** @var array<int, array{id: string, created: string, author?: array<string, mixed>, items?: array<int, array<string, mixed>>}> $histories */
        $histories = $data['changelog']['histories'] ?? [];
        $renderedCommentsById = collect($renderedComments)->keyBy('id');

        return new JiraIssueDetails(
            issue: new JiraIssue(
                key: $data['key'],
                issueType: $fields['issuetype']['name'] ?? '',
                summary: $fields['summary'] ?? '',
                createdAt: CarbonImmutable::parse($fields['created']),
                resolvedAt: isset($fields['resolutiondate']) ? CarbonImmutable::parse($fields['resolutiondate']) : null,
            ),
            comments: collect($comments)->map(function (array $comment) use ($renderedCommentsById): JiraComment {
                $rendered = $renderedCommentsById->get($comment['id']);
                // The raw body is Atlassian Document Format, so prefer the rendered HTML as plain text.
                $body = $rendered ? strip_tags((string) $rendered['body']) : ($comment['body'] ?? '');

                return new JiraComment(
                    id: $comment['id'],
                    authorDisplayName: $comment['author']['displayName'] ?? '',
                    authorEmail: $comment['author']['emailAddress'] ?? null,
                    body: trim($body),
                    createdAt: CarbonImmutable::parse($comment['created']),
                );
            }),
            statusChanges: collect($histories)->flatMap(fn (array $history): array => collect($history['items'] ?? [])
                ->filter(fn (array $item): bool => $item['field'] === 'status')
                ->map(fn (array $item, int $index): JiraChangelogEntry => new JiraChangelogEntry(
                    id: $history['id'].'-'.$index,
                    authorDisplayName: $history['author']['displayName'] ?? '',
                    authorEmail: $history['author']['emailAddress'] ?? null,
                    fromStatus: $item['fromString'] ?? '',
                    toStatus: $item['toString'] ?? '',
                    createdAt: CarbonImmutable::parse($history['created']),
                ))
                ->values()
                ->all()),
        );
    }
}
