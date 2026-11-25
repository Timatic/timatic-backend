<?php

declare(strict_types=1);

namespace Timatic\Bitbucket;

use App\DataTransferObjects\GitRemote;
use App\DataTransferObjects\GitRepositoryMapping;
use App\Integrations\Git\GitProviderInterface;
use Timatic\Bitbucket\Filament\Pages\RepositoryMappingPage;
use Timatic\Bitbucket\Models\RepositoryMapping;

final class GitProvider implements GitProviderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private array $config) {}

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): static
    {
        return new self($config);
    }

    public static function hosts(): array
    {
        return ['bitbucket.org'];
    }

    public function findMappingByRemote(GitRemote $remote): ?GitRepositoryMapping
    {
        [$workspaceSlug, $repositorySlug] = explode('/', strtolower($remote->path), 2);

        $mapping = RepositoryMapping::query()
            ->where('integration_id', $this->config['integration_id'])
            ->whereRaw('lower(workspace_slug) = ?', [$workspaceSlug])
            ->whereRaw('lower(repository_slug) = ?', [$repositorySlug])
            ->active()
            ->first();

        if ($mapping?->customer_id === null) {
            return null;
        }

        return new GitRepositoryMapping($remote->name(), $mapping->customer_id, $mapping->budget_id);
    }

    public function isMapped(string $repositoryIdentifier): bool
    {
        [$workspaceSlug, $repositorySlug] = explode('/', $repositoryIdentifier, 2) + [1 => ''];

        return RepositoryMapping::query()
            ->where('integration_id', $this->config['integration_id'])
            ->where('workspace_slug', $workspaceSlug)
            ->where('repository_slug', $repositorySlug)
            ->whereNotNull('customer_id')
            ->exists();
    }

    public function mappingPageUrl(string $repositoryIdentifier): string
    {
        // the page lists a single workspace, so the slug alone finds the repository
        $repositorySlug = explode('/', $repositoryIdentifier, 2)[1] ?? $repositoryIdentifier;

        return RepositoryMappingPage::getUrl(['record' => $this->config['integration_id'], 'search' => $repositorySlug], panel: 'admin');
    }
}
