<?php

declare(strict_types=1);

namespace Timatic\Jira;

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
        return __('jira::jira.integration.label');
    }

    public function instructions(): string
    {
        return __('jira::jira.integration.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return Integration::query()->where('type', 'jira')->exists();
    }

    public function isActive(): bool
    {
        if (blank(config('jira.client_id')) || blank(config('jira.client_secret'))) {
            return false;
        }

        return Integration::query()->where('type', 'jira')->get()->contains(
            fn (Integration $integration): bool => filled($integration->config['access_token'] ?? null)
                && filled($integration->config['cloud_id'] ?? null),
        );
    }

    public function isActiveFor(User $user): bool
    {
        return $this->isActive();
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
