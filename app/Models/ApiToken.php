<?php

namespace App\Models;

use App\DataTransferObjects\DerivedPermission;
use App\Models\User as TimaticUser;
use Carbon\Carbon;
use Database\Factories\ApiTokenFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property ?int $id
 * @property ?int $user_id
 * @property ?TimaticUser $user
 * @property ?string $external_id
 * @property ?string $title
 * @property ?string $description
 * @property ?string $key
 * @property ?Carbon $expires_at
 * @property ?Carbon $last_used_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 *
 * @method static ApiToken|Builder<ApiToken> notExpired()
 */
class ApiToken extends User implements Authenticatable
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    use HasPermissions;
    use HasRoles;

    protected $guarded = [];

    /**
     * @var array<int, DerivedPermission>
     */
    public array $derivedPermissions = [];

    /**
     * @return BelongsTo<TimaticUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TimaticUser::class);
    }

    public function touchLastUsed(): void
    {
        if ($this->last_used_at?->isAfter(Carbon::now()->subMinute())) {
            return;
        }

        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }

    /**
     * @param  Builder<ApiToken>  $query
     */
    #[Scope]
    protected function notExpired(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * @return Collection<int, string>
     */
    protected function getGuardNames(): Collection
    {
        return new Collection(['web']);
    }

    protected function getDefaultGuardName(): string
    {
        return 'web';
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
        ];
    }
}
