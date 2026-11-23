<?php

namespace Timatic\Jira;

use App\Models\Integration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\DataTransferObjects\JiraUser;
use Timatic\Jira\Requests\GetIssuesRequest;
use Timatic\Jira\Requests\GetUserByAccountIdRequest;
use Timatic\Jira\Requests\GetUserByEmailRequest;

/**
 * Looks up issues and accounts of a connected Jira instance for other integrations,
 * so they never deal with Jira's connector, token refresh or requests themselves.
 */
final class JiraDirectory
{
    /** @var array<int, Connector> */
    private array $connectors = [];

    public function __construct(private readonly OAuthService $oauthService) {}

    /** @phpstan-assert-if-true Integration $jira */
    public function isConnected(?Integration $jira): bool
    {
        return filled($jira?->config['access_token'] ?? null) && filled($jira->config['cloud_id'] ?? null);
    }

    /**
     * @param  Collection<int, string>  $issueIds
     * @return Collection<string, JiraIssue> keyed by issue id
     */
    public function issuesByIds(Integration $jira, Collection $issueIds): Collection
    {
        return $issueIds->unique()->chunk(50)
            ->flatMap(function (Collection $chunk) use ($jira): array {
                /** @var array<int, JiraIssue> $issues */
                $issues = $this->connector($jira)->send(new GetIssuesRequest('id in ('.$chunk->implode(', ').')'))->dto();

                return $issues;
            })
            ->keyBy(fn (JiraIssue $issue) => (string) $issue->id);
    }

    public function findIssueId(Integration $jira, string $issueKey): ?int
    {
        return Cache::rememberForever('jira.issue_id.'.strtoupper($issueKey), function () use ($jira, $issueKey): ?int {
            /** @var array<int, JiraIssue> $issues */
            $issues = $this->connector($jira)->send(new GetIssuesRequest('key = "'.strtoupper($issueKey).'"'))->dto();
            $id = $issues[0]->id ?? null;

            return $id === null ? null : (int) $id;
        });
    }

    public function user(Integration $jira, string $accountId): JiraUser
    {
        $user = $this->connector($jira)->send(new GetUserByAccountIdRequest($accountId))->dto();

        if ($user->emailAddress !== '') {
            Cache::forever('jira.email.'.$accountId, $user->emailAddress);
            Cache::forever('jira.account_id.'.strtolower($user->emailAddress), $accountId);
        }

        return $user;
    }

    /** Lower-cased e-mail address of the account, empty when Jira hides it */
    public function email(Integration $jira, string $accountId): string
    {
        return strtolower(Cache::get('jira.email.'.$accountId) ?? $this->user($jira, $accountId)->emailAddress);
    }

    public function findAccountId(Integration $jira, string $email): ?string
    {
        return Cache::rememberForever('jira.account_id.'.strtolower($email), function () use ($jira, $email): ?string {
            /** @var ?JiraUser $user */
            $user = $this->connector($jira)->send(new GetUserByEmailRequest($email))->dto();

            return $user?->accountId;
        });
    }

    private function connector(Integration $jira): Connector
    {
        return $this->connectors[$jira->id] ??= new Connector($this->oauthService->refreshIfExpired($jira)->config);
    }
}
