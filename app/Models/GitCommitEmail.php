<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email address a Timatic user authors commits with, stored lowercase.
 *
 * @property int $user_id
 * @property string $email
 */
class GitCommitEmail extends Model
{
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
