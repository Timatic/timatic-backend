<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $entry_id
 * @property ?int $customer_id
 * @property ?string $customer_name
 * @property ?int $user_id
 * @property ?string $user_full_name
 * @property ?int $user_team_id
 * @property ?Carbon $started_at
 * @property ?Carbon $ended_at
 * @property ?int $internal_minutes
 * @property ?int $budget_minutes
 * @property ?int $paid_per_hour_minutes
 * @property ?User $user
 * @property ?Customer $customer
 * @property ?Entry $entry
 * @property ?Team $team
 */
class UserCustomerHoursRecord extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'user_team_id');
    }
}
