<?php

declare(strict_types=1);

namespace Timatic\GitHub\Commands;

use App\Models\Integration;
use App\Models\Repository;
use Illuminate\Console\Command;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Timatic\GitHub\Connector;
use Timatic\GitHub\DataTransferObjects\GitHubCommit;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationService;
use Timatic\GitHub\Models\RepositoryMapping;
use Timatic\GitHub\Requests\GetRepositoryCommitsRequest;
use Timatic\GitHub\UserMatcher;

/**
 * GitHub only sends webhooks for mapped repositories, so work elsewhere in the connected
 * organisations is found by the commits of Timatic users in recently pushed repositories.
 */
class ScanRepositoriesCommand extends Command
{
    protected $signature = 'github:scan-repositories';

    protected $description = 'Record recent work of Timatic users in GitHub repositories without a customer';

    public function handle(InstallationService $installations, UserMatcher $userMatcher): void
    {
        $since = now()->subWeek();

        foreach (Integration::where('type', 'github')->get() as $integration) {
            $skippedFullNames = RepositoryMapping::query()
                ->where('integration_id', $integration->id)
                ->whereNotNull('customer_id')
                ->pluck('repository_full_name')
                ->merge(Repository::where('integration_id', $integration->id)->where('is_ignored', true)->pluck('identifier'))
                ->map(fn (string $fullName): string => strtolower($fullName));

            try {
                foreach (array_keys($installations->stored($integration->config ?? [])) as $installationId) {
                    $connector = Connector::forInstallation($installationId);

                    foreach ($installations->repositories($installationId) as $repository) {
                        if ($repository->pushedAt === null || $repository->pushedAt->lt($since) || $skippedFullNames->contains(strtolower($repository->fullName))) {
                            continue;
                        }

                        $response = $connector->send(new GetRepositoryCommitsRequest($repository->fullName, $since));

                        // GitHub answers 409 for a repository without commits
                        if ($response->status() === 409) {
                            continue;
                        }

                        if ($response->failed()) {
                            throw new GitHubException('Fetching the commits of '.$repository->fullName.' failed with status '.$response->status().'.');
                        }

                        /** @var array<int, GitHubCommit> $commits */
                        $commits = $response->dto();
                        $latestCommitOfUser = collect($commits)
                            ->filter(fn (GitHubCommit $commit): bool => $userMatcher->forCommitAuthor($commit->authorEmail, $commit->authorLogin) !== null)
                            ->sortByDesc(fn (GitHubCommit $commit): int => $commit->committedAt->getTimestamp())
                            ->first();

                        if ($latestCommitOfUser !== null) {
                            Repository::recordWork($integration->id, $repository->fullName, $latestCommitOfUser->committedAt);
                        }
                    }
                }
            } catch (GitHubException|RequestException|FatalRequestException $e) {
                report($e);
            }
        }
    }
}
