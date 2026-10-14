<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Models\ApiToken;
use App\Models\User;

class ChromeExtensionIntegration implements SuggestionIntegrationInterface
{
    public const CLIENT_ID = 'chrome-extension';

    public function sourceId(): string
    {
        return 'browser';
    }

    public function label(): string
    {
        return __('integrations.chrome_extension.label');
    }

    public function instructions(): string
    {
        return __('integrations.chrome_extension.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isActive(): bool
    {
        return true;
    }

    public function isActiveFor(User $user): bool
    {
        return ApiToken::query()
            ->where('user_id', $user->id)
            ->where('client_id', self::CLIENT_ID)
            ->notExpired()
            ->exists();
    }

    public function connectUrl(User $user): ?string
    {
        return config('services.chrome_extension.install_url');
    }

    public function connectLabel(): string
    {
        return __('integrations.install');
    }
}
