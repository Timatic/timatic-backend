<?php

namespace App\Models;

use App\DataTransferObjects\DerivedPermission;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property ?int $id
 * @property ?int $team_id
 * @property ?string $external_id
 * @property ?string $email
 * @property ?string $given_name
 * @property ?string $family_name
 * @property ?string $full_name
 * @property ?string $bitbucket_account_id
 * @property ?string $oauth_access_token
 * @property ?string $oauth_refresh_token
 * @property int $oauth_token_expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property ?Team $team
 * @property Collection<int, Permission> $permissions
 */
class User extends \Illuminate\Foundation\Auth\User implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPermissions;
    use HasRoles;
    use SoftDeletes;

    protected ?int $isImpersonatedById = null;

    protected $fillable = [
        'external_id',
        'email',
        'given_name',
        'family_name',
        'bitbucket_account_id',
        'oauth_access_token',
        'oauth_refresh_token',
        'oauth_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_token_expires_at' => 'integer',
        ];
    }

    public function isOAuthConnected(): bool
    {
        return filled($this->oauth_access_token) && filled($this->oauth_refresh_token);
    }

    /** @var array<int, DerivedPermission> */
    public array $derivedPermissions = [];

    /** @return SupportCollection<int, string> */
    protected function getGuardNames(): SupportCollection
    {
        return new SupportCollection(['web']);
    }

    public function getFilamentName(): string
    {
        return $this->given_name.' '.$this->family_name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasPermissionTo('admin_panel.access') || $this->hasRole('super_admin');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->given_name.' '.$this->family_name;
    }

    public function impersonate(): void
    {
        $this->isImpersonatedById = Auth::user()?->id;
    }

    public function isImpersonated(): bool
    {
        return $this->isImpersonatedById !== null;
    }

    public function impersonatedById(): ?int
    {
        return $this->isImpersonatedById;
    }
}
