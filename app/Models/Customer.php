<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property ?int $id
 * @property ?string $external_id
 * @property ?string $name
 * @property ?string $hourly_rate
 * @property bool $is_own_organization
 * @property ?int $account_manager_user_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property ?User $accountManager
 * @property Collection<int, TrackedDomain> $trackedDomains
 * @property Collection<int, CustomerEmailDomain> $emailDomains
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'external_id',
        'name',
        'hourly_rate',
        'is_own_organization',
        'account_manager_user_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_user_id');
    }

    /**
     * @return HasMany<TrackedDomain, $this>
     */
    public function trackedDomains(): HasMany
    {
        return $this->hasMany(TrackedDomain::class);
    }

    /**
     * @return HasMany<CustomerEmailDomain, $this>
     */
    public function emailDomains(): HasMany
    {
        return $this->hasMany(CustomerEmailDomain::class);
    }

    protected static function booted(): void
    {
        // Soft deletes too: a deleted customer must not keep a domain another customer may need.
        static::deleting(function (Customer $customer) {
            $customer->emailDomains()->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'is_own_organization' => 'boolean',
        ];
    }
}
