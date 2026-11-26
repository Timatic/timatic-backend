<?php

namespace Timatic\Jira\Models;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $integration_id
 * @property string $account_id
 * @property string $display_name
 * @property ?string $email
 * @property ?int $user_id
 */
class UserMapping extends Model
{
    protected $table = 'jira_user_mappings';

    protected $fillable = [
        'integration_id',
        'account_id',
        'display_name',
        'email',
        'user_id',
    ];

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
