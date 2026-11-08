<?php

declare(strict_types=1);

namespace Timatic\GitHub\Commands;

use App\Models\Integration;
use App\Models\Repository;
use Illuminate\Console\Command;
use Timatic\GitHub\Exceptions\GitHubException;
use Timatic\GitHub\InstallationService;
use Timatic\GitHub\Models\RepositoryMapping;

/**
 * GitHub only sends webhooks for mapped repositories, so work elsewhere in the
 * connected organisations is found by the last push of each repository.
 */
class ScanRepositoriesCommand extends Command
{
    protected $signature = 'github:scan-repositories';

    protected $description = 'Record recent pushes to GitHub repositories without a customer';

    public function handle(InstallationService $installations): void
    {
        foreach (Integration::where('type', 'github')->get() as $integration) {
            $mappedFullNames = RepositoryMapping::query()
                ->where('integration_id', $integration->id)
                ->whereNotNull('customer_id')
                ->pluck('repository_full_name')
                ->map(fn (string $fullName): string => strtolower($fullName));

            foreach (array_keys($installations->stored($integration->config ?? [])) as $installationId) {
                try {
                    $repositories = $installations->repositories($installationId);
                } catch (GitHubException $e) {
                    report($e);

                    continue;
                }

                foreach ($repositories as $repository) {
                    if ($repository->pushedAt === null || $repository->pushedAt->lt(now()->subWeek()) || $mappedFullNames->contains(strtolower($repository->fullName))) {
                        continue;
                    }

                    Repository::recordWork($integration->id, $repository->fullName, $repository->name, $repository->pushedAt);
                }
            }
        }
    }
}
