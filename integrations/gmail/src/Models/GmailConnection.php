<?php

declare(strict_types=1);

namespace Timatic\Gmail\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Timatic\Gmail\Database\Factories\GmailConnectionFactory;

/**
 * A user's grant to read the headers of their Gmail. It lives here rather than on the user because it is
 * this integration's to hold: a user without one is not half connected, they simply never asked for
 * their mail to be read.
 *
 * @property ?int $id
 * @property ?int $user_id
 * @property ?User $user
 * @property ?string $access_token
 * @property ?string $refresh_token
 * @property ?CarbonImmutable $expires_at
 * @property ?string $history_id the point in the mailbox history the next sync reads on from
 */
class GmailConnection extends Model
{
    /** @use HasFactory<GmailConnectionFactory> */
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

    protected static function newFactory(): GmailConnectionFactory
    {
        return new GmailConnectionFactory;
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
