<?php

declare(strict_types=1);

namespace App\Integrations\Git;

use App\DataTransferObjects\GitRemote;
use App\DataTransferObjects\GitRepositoryMapping;
use App\Models\Integration;

class GitRepositoryService
{
    public function __construct(private readonly GitProviderRegistry $registry) {}

    public function findMappingByRemote(GitRemote $remote): ?GitRepositoryMapping
    {
        $types = array_keys(array_filter(
            $this->registry->all(),
            fn (string $class) => in_array($remote->host, $class::hosts(), true),
        ));

        foreach (Integration::whereIn('type', $types)->get() as $integration) {
            $mapping = $this->registry
                ->makeProvider($integration->type, [...($integration->config ?? []), 'integration_id' => $integration->id])
                ->findMappingByRemote($remote);

            if ($mapping !== null) {
                return $mapping;
            }
        }

        return null;
    }
}
