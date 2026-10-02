<?php

namespace Timatic\GoogleCalendar\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Timatic\GoogleCalendar\Database\Factories\GoogleCalendarConnectionFactory;

/**
 * A user's grant to read their Google Calendar. It lives here rather than on the user because it is
 * this integration's to hold: a user without one is not half connected, they simply never asked for
 * their calendar to be read.
 *
 * @property ?int $id
 * @property ?int $user_id
 * @property ?User $user
 * @property ?string $access_token
 * @property ?string $refresh_token
 * @property ?CarbonImmutable $expires_at
 */
class GoogleCalendarConnection extends Model
{
    /** @use HasFactory<GoogleCalendarConnectionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    protected static function newFactory(): GoogleCalendarConnectionFactory
    {
        return new GoogleCalendarConnectionFactory;
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
