<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataTransferObjects\UnmappedRepository;
use App\Integrations\Git\GitProviderInterface;
use App\Integrations\Git\GitProviderRegistry;
use App\Mail\UnmappedRepositoriesDigest;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendUnmappedRepositoriesDigestCommand extends Command
{
    protected $signature = 'repositories:unmapped-digest';

    protected $description = 'Mail the admins which repositories without a customer had work recently';

    public function handle(GitProviderRegistry $gitProviders): void
    {
        $providerOf = fn (Repository $repository): GitProviderInterface => $gitProviders->makeProvider(
            $repository->integration->type,
            [...($repository->integration->config ?? []), 'integration_id' => $repository->integration_id],
        );

        $repositories = Repository::dueForDigest()
            ->with('integration')
            ->get()
            // mapped since the work was recorded
            ->reject(fn (Repository $repository): bool => $providerOf($repository)->isMapped($repository->identifier));

        if ($repositories->isEmpty()) {
            return;
        }

        // filtered through the policy so super admins, who pass via Shield's gate instead of a permission, are included
        foreach (User::all() as $user) {
            $userRepositories = $repositories
                ->filter(fn (Repository $repository): bool => $user->can('update', $repository->integration))
                ->map(fn (Repository $repository): UnmappedRepository => new UnmappedRepository(
                    $repository->integration->display_title,
                    $repository->identifier,
                    $providerOf($repository)->mappingPageUrl($repository->identifier),
                ))
                ->values()
                ->toBase();

            if ($userRepositories->isNotEmpty()) {
                Mail::to($user->email)->send(new UnmappedRepositoriesDigest($userRepositories));
            }
        }

        Repository::whereKey($repositories->modelKeys())->update(['last_notified_at' => now()]);
    }
}
