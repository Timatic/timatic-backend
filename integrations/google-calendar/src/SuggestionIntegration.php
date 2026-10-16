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
    public function sourceId(): string
    {
        return ServiceProvider::SOURCE_ID;
    }

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

    public function isActive(): bool
    {
        return filled(config('google_calendar.client_id'))
            && filled(config('google_calendar.client_secret'))
            && filled(config('google_calendar.redirect'));
    }

    public function isActiveFor(User $user): bool
    {
        return $this->isActive() && GoogleCalendarConnection::query()->where('user_id', $user->id)->exists();
    }

    /**
     * The link is handed out with the list, so it lasts long enough for a user to read the
     * instructions first. Without credentials Google would only show an error, so none is offered.
     */
    public function connectUrl(User $user): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        return URL::temporarySignedRoute('google_calendar.connect', now()->addHour(), ['user' => $user->id]);
    }

    public function connectLabel(): string
    {
        return __('integrations.connect');
    }
}
