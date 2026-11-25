<?php

namespace Timatic\Jira\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasRequestPagination;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\PaginationPlugin\OffsetPaginator;
use Timatic\Jira\DataTransferObjects\JiraUser;

class GetUsersRequest extends Request implements HasRequestPagination, Paginatable
{
    public const LIMIT = 100;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/users/search';
    }

    /**
     * Only people: app and service desk customer accounts never log time.
     *
     * @return Collection<int, JiraUser>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var array<int, array<string, mixed>> $users */
        $users = $response->json();

        return collect($users)
            ->filter(fn (array $user): bool => ($user['accountType'] ?? '') === 'atlassian')
            ->map(fn (array $user): JiraUser => new JiraUser(
                accountId: (string) $user['accountId'],
                emailAddress: (string) ($user['emailAddress'] ?? ''),
                displayName: (string) ($user['displayName'] ?? ''),
            ))
            ->values();
    }

    public function paginate(Connector $connector): OffsetPaginator
    {
        return new class(connector: $connector, request: $this) extends OffsetPaginator
        {
            protected ?int $perPageLimit = GetUsersRequest::LIMIT;

            protected function applyPagination(Request $request): Request
            {
                $request->query()->merge(['startAt' => $this->getOffset(), 'maxResults' => $this->perPageLimit]);

                return $request;
            }

            // Jira returns a bare array without paging metadata
            protected function isLastPage(Response $response): bool
            {
                return count($response->json()) < GetUsersRequest::LIMIT;
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->dto()->all();
            }
        };
    }
}
