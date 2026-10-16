<?php

declare(strict_types=1);

namespace Timatic\ClaudeCode;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Models\ApiToken;
use App\Models\User;

/**
 * The Claude Code plugin logs a user's prompts and answers as events. Each user installs it and
 * logs in on their own machine, so it is never switched on for everyone.
 */
class SuggestionIntegration implements SuggestionIntegrationInterface
{
    public function sourceId(): string
    {
        return ServiceProvider::SOURCE_ID;
    }

    public function label(): string
    {
        return __('claude_code::claude_code.integration.label');
    }

    public function instructions(): string
    {
        return __('claude_code::claude_code.integration.instructions');
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
            ->where('client_id', ServiceProvider::CLIENT_ID)
            ->notExpired()
            ->exists();
    }

    public function connectUrl(User $user): ?string
    {
        return config('claude_code.plugin_url');
    }

    public function connectLabel(): string
    {
        return __('integrations.install');
    }
}
