<?php

declare(strict_types=1);

namespace Timatic\Bitbucket;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Models\Integration;
use App\Models\User;

class SuggestionIntegration implements SuggestionIntegrationInterface
{
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
        return $this->isAvailable();
    }

    public function connectUrl(User $user): ?string
    {
        return null;
    }
}
