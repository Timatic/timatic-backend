<?php

declare(strict_types=1);

namespace Timatic\Bitbucket;

use App\DataTransferObjects\GitRemote;
use App\DataTransferObjects\GitRepositoryMapping;
use App\Integrations\Contracts\GitProviderInterface;
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
}
