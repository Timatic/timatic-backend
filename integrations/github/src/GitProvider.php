<?php

declare(strict_types=1);

namespace Timatic\GitHub;

use App\DataTransferObjects\GitRemote;
use App\DataTransferObjects\GitRepositoryMapping;
use App\Integrations\Git\GitProviderInterface;
use Timatic\GitHub\Models\RepositoryMapping;

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
        return ['github.com'];
    }

    public function findMappingByRemote(GitRemote $remote): ?GitRepositoryMapping
    {
        $mapping = RepositoryMapping::query()
            ->where('integration_id', $this->config['integration_id'])
            ->whereRaw('lower(repository_full_name) = ?', [strtolower($remote->path)])
            ->active()
            ->first();

        if ($mapping?->customer_id === null) {
            return null;
        }

        return new GitRepositoryMapping($remote->name(), $mapping->customer_id, $mapping->budget_id);
    }
}
