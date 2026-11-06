<?php

namespace Timatic\GitHub\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The GitHub login of a Timatic user, matched against the sender of webhook deliveries.
 *
 * @property int $user_id
 * @property string $login
 */
class UserLogin extends Model
{
    protected $table = 'github_user_logins';

    protected $fillable = [
        'user_id',
        'login',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
