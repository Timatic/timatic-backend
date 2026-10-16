<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use App\Models\User;

interface SuggestionIntegrationInterface
{
    public function sourceId(): string;

    public function label(): string;

    /** Markdown explaining how the integration turns a user's work into suggestions. */
    public function instructions(): string;

    /** Company managed integrations are switched on by an admin for everyone, not by the user. */
    public function isCompanyManaged(): bool;

    public function isAvailable(): bool;

    /** Company managed integrations are active for everyone once an admin finished setting them up. */
    public function isActiveFor(User $user): bool;

    public function connectUrl(User $user): ?string;

    public function connectLabel(): ?string;
}
