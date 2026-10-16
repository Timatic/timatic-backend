<?php

declare(strict_types=1);

namespace Timatic\Bitbucket;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Models\Integration;
use App\Models\User;

class SuggestionIntegration implements SuggestionIntegrationInterface
{
    public function sourceId(): string
    {
        return ServiceProvider::SOURCE_ID;
    }

    public function label(): string
    {
        return __('bitbucket::bitbucket.integration.label');
    }

    public function instructions(): string
    {
        return __('bitbucket::bitbucket.integration.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return Integration::query()->where('type', 'bitbucket')->exists();
    }

    public function isActiveFor(User $user): bool
    {
        return Integration::query()->where('type', 'bitbucket')->get()->contains(
            fn (Integration $integration): bool => filled($integration->config['access_token'] ?? null)
                && filled($integration->config['workspace'] ?? null)
                && filled($integration->config['webhook_uuid'] ?? null),
        );
    }

    public function connectUrl(User $user): ?string
    {
        return null;
    }

    public function connectLabel(): ?string
    {
        return null;
    }
}
