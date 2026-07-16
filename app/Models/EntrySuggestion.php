<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\EntrySuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property ?int $id
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?string $customer_id
 * @property ?string $user_id
 * @property ?Carbon $date
 * @property ?string $ticket_title
 * @property ?string $ticket_type
 * @property ?string $customer_name
 * @property ?int $budget_id
 * @property ?bool $is_internal
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?User $user
 * @property Collection<int, Activity> $activities
 * @property ?Entry $entry
 */
class EntrySuggestion extends Model
{
    /** @use HasFactory<EntrySuggestionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'customer_id',
        'user_id',
        'date',
        'ticket_title',
        'ticket_type',
        'customer_name',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_internal' => 'bool',
            'date' => 'date',
        ];
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasOne<Entry, $this>
     */
    public function entry(): HasOne
    {
        return $this->hasOne(Entry::class);
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
}
