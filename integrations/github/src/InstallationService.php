<?php

namespace Timatic\GitHub;

use App\Models\Integration;
use Timatic\GitHub\DataTransferObjects\GitHubInstallation;
use Timatic\GitHub\DataTransferObjects\GitHubRepository;
use Timatic\GitHub\DataTransferObjects\GitHubRepositoryPage;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\Requests\GetInstallationRepositoriesRequest;
use Timatic\GitHub\Requests\GetUserInstallationsRequest;

/**
 * Every installation the connecting user can reach is adopted: the app is only
 * installed by the customer's own organisations, so there is nothing to choose.
 */
class InstallationService
{
    /** @return array<int, GitHubInstallation> */
    public function refresh(Integration $integration): array
    {
        $integration = app(OAuthService::class)->refreshIfExpired($integration);

        $response = Connector::forUser($integration->config['access_token'] ?? '')
            ->send(new GetUserInstallationsRequest);

        if ($response->failed()) {
            throw new GitHubException('Fetching the app installations failed with status '.$response->status().'.');
        }

        /** @var array<int, GitHubInstallation> $installations */
        $installations = $response->dto();

        $integration->update([
            'config' => array_merge($integration->config ?? [], [
                'installations' => array_map(
                    fn (GitHubInstallation $installation) => [
                        'id' => $installation->id,
                        'account' => $installation->accountLogin,
                    ],
                    $installations,
                ),
            ]),
        ]);

        return $installations;
    }

    /**
     * Stored so repositories and issues keep syncing when the user token lapses;
     * only discovering new installations needs the user.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string> installation id => account login
     */
    public function stored(array $config): array
    {
        $installations = [];

        foreach ($config['installations'] ?? [] as $installation) {
            $id = (int) ($installation['id'] ?? 0);

            if ($id !== 0) {
                $installations[$id] = (string) ($installation['account'] ?? '');
            }
        }

        return $installations;
    }

    /**
     * @return array<int, GitHubRepository>
     *
     * @throws GitHubException
     */
    public function repositories(int $installationId): array
    {
        $connector = Connector::forInstallation($installationId);
        $repositories = [];
        $page = 1;

        do {
            $response = $connector->send(new GetInstallationRepositoriesRequest($page));

            if ($response->failed()) {
                throw new GitHubException('Fetching installation repositories failed with status '.$response->status().'.');
            }

            /** @var GitHubRepositoryPage $repositoryPage */
            $repositoryPage = $response->dto();
            $repositories = [...$repositories, ...$repositoryPage->repositories];
            $page++;
        } while ($repositoryPage->repositories !== [] && count($repositories) < $repositoryPage->totalCount);

        return $repositories;
    }
}
