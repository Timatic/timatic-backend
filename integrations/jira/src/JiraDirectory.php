<?php

namespace Timatic\Jira;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Timatic\Jira\DataTransferObjects\JiraIssue;
use Timatic\Jira\DataTransferObjects\JiraUser;
use Timatic\Jira\Models\UserMapping;
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
        return Connector::hasCredentials($jira->config ?? []);
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

    /**
     * @param  Collection<int, string>  $accountIds
     * @return Collection<string, User> keyed by account id, without the accounts that have no Timatic user
     */
    public function timaticUsers(Integration $jira, Collection $accountIds): Collection
    {
        $mappings = UserMapping::query()->where('integration_id', $jira->id)->whereIn('account_id', $accountIds)->get()->keyBy('account_id');

        return $accountIds->unique()
            ->mapWithKeys(function (string $accountId) use ($jira, $mappings): array {
                $existing = $mappings->get($accountId);
                $mapping = $existing !== null
                    ? $this->linkByEmail($existing)
                    : $this->remember($jira, $this->connector($jira)->send(new GetUserByAccountIdRequest($accountId))->dto());

                return [$accountId => $mapping->user];
            })
            ->filter();
    }

    public function findAccountId(Integration $jira, User $user): ?string
    {
        $accountId = UserMapping::query()->where('integration_id', $jira->id)->where('user_id', $user->id)->value('account_id');

        if ($accountId !== null) {
            return $accountId;
        }

        /** @var ?JiraUser $jiraUser */
        $jiraUser = $this->connector($jira)->send(new GetUserByEmailRequest($user->email))->dto();

        return $jiraUser === null ? null : $this->remember($jira, $jiraUser)->account_id;
    }

    private function connector(Integration $jira): Connector
    {
        return $this->connectors[$jira->id] ??= new Connector($this->oauthService->refreshIfExpired($jira)->config);
    }

    private function remember(Integration $jira, JiraUser $user): UserMapping
    {
        $mapping = UserMapping::query()->updateOrCreate(
            ['integration_id' => $jira->id, 'account_id' => $user->accountId],
            ['display_name' => $user->displayName !== '' ? $user->displayName : $user->accountId, 'email' => $user->emailAddress !== '' ? $user->emailAddress : null],
        );

        return $this->linkByEmail($mapping);
    }

    private function linkByEmail(UserMapping $mapping): UserMapping
    {
        if ($mapping->user_id === null && $mapping->email !== null) {
            $mapping->user()->associate(User::query()->where('email', $mapping->email)->first())->save();
        }

        return $mapping;
    }
}
