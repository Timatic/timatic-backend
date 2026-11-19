<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraWorklogFeedPage;

class GetUpdatedWorklogIdsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly int $sinceMilliseconds) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/worklog/updated';
    }

    protected function defaultQuery(): array
    {
        return ['since' => $this->sinceMilliseconds];
    }

    public function createDtoFromResponse(Response $response): JiraWorklogFeedPage
    {
        /** @var array<int, array{worklogId: int|string}> $values */
        $values = $response->json('values') ?? [];

        return new JiraWorklogFeedPage(
            worklogIds: collect($values)->map(fn (array $value) => (string) $value['worklogId']),
            until: (int) $response->json('until', $this->sinceMilliseconds),
            isLastPage: (bool) $response->json('lastPage', true),
        );
    }
}
