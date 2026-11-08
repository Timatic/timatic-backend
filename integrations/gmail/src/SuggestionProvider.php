<?php

declare(strict_types=1);

namespace Timatic\Gmail;

use App\Integrations\Suggestions\SuggestionProviderInterface;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Timatic\Gmail\Models\GmailConnection;

class SuggestionProvider implements SuggestionProviderInterface
{
    public function sourceId(): string
    {
        return ServiceProvider::SOURCE_ID;
    }

    public function label(): string
    {
        return __('gmail::gmail.integration.label');
    }

    public function instructions(): string
    {
        return __('gmail::gmail.integration.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        return Integration::query()->where('type', 'gmail')->exists();
    }

    public function isActiveFor(User $user): bool
    {
        return GmailConnection::query()->where('user_id', $user->id)->exists();
    }

    /** The link is handed out with the list, so it lasts long enough for a user to read the instructions first. */
    public function connectUrl(User $user): string
    {
        return URL::temporarySignedRoute('gmail.connect', now()->addHour(), ['user' => $user->id]);
    }

    public function connectLabel(): string
    {
        return __('integrations.connect');
    }
}
