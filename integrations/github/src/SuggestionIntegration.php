<?php

declare(strict_types=1);

namespace Timatic\GitHub;

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
        return __('github::github.integration.label');
    }

    public function instructions(): string
    {
        return __('github::github.integration.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return Integration::query()->where('type', 'github')->exists();
    }

    public function isActiveFor(User $user): bool
    {
        return Integration::query()->where('type', 'github')->get()->contains(
            fn (Integration $integration): bool => app(InstallationService::class)->stored($integration->config ?? []) !== [],
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
