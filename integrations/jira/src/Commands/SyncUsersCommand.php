<?php

namespace Timatic\Jira\Commands;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Console\Command;
use Timatic\Jira\Connector;
use Timatic\Jira\DataTransferObjects\JiraUser;
use Timatic\Jira\Models\UserMapping;
use Timatic\Jira\OAuthService;
use Timatic\Jira\Requests\GetUsersRequest;

class SyncUsersCommand extends Command
{
    protected $signature = 'jira:sync-users {integration : Id of the Jira integration}';

    protected $description = 'Store every person of a Jira instance in its user mapping and link the new ones to a Timatic user by e-mail';

    public function handle(OAuthService $oauthService): int
    {
        $jira = Integration::query()->where('type', 'jira')->findOrFail($this->argument('integration'));
        $connector = new Connector($oauthService->refreshIfExpired($jira)->config);

        /** @var JiraUser $user */
        foreach ((new GetUsersRequest)->paginate($connector)->items() as $user) {
            $mapping = UserMapping::query()->updateOrCreate(
                ['integration_id' => $jira->id, 'account_id' => $user->accountId],
                [
                    'display_name' => $user->displayName !== '' ? $user->displayName : $user->accountId,
                    'email' => $user->emailAddress !== '' ? $user->emailAddress : null,
                ],
            );

            if ($mapping->user_id === null && $mapping->email !== null) {
                $mapping->user()->associate(User::query()->where('email', $mapping->email)->first())->save();
            }
        }

        return self::SUCCESS;
    }
}
