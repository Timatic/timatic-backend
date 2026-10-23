<?php

namespace Timatic\GitHub\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email address a Timatic user authors commits with, stored lowercase.
 *
 * @property int $user_id
 * @property string $email
 */
class CommitEmail extends Model
{
    protected $table = 'github_commit_emails';

    protected $fillable = [
        'user_id',
        'email',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
