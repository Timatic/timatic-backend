<?php

declare(strict_types=1);

namespace Timatic\GoogleCalendar;

use App\Integrations\Contracts\SuggestionIntegrationInterface;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Timatic\GoogleCalendar\Models\GoogleCalendarConnection;

class SuggestionIntegration implements SuggestionIntegrationInterface
{
    public function label(): string
    {
        return __('google_calendar::google_calendar.integration.label');
    }

    public function instructions(): string
    {
        return __('google_calendar::google_calendar.integration.instructions');
    }

    public function isCompanyManaged(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        return Integration::query()->where('type', 'google_calendar')->exists();
    }

    public function isActiveFor(User $user): bool
    {
        return GoogleCalendarConnection::query()->where('user_id', $user->id)->exists();
    }

    /** The api authenticates with a bearer token a browser redirect cannot carry, so the url vouches for the user instead. */
    public function connectUrl(User $user): string
    {
        return URL::temporarySignedRoute('google_calendar.connect', now()->addMinutes(5), ['user' => $user->id]);
    }
}
