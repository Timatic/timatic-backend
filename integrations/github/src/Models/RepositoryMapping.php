<?php

namespace Timatic\GitHub\Models;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $integration_id
 * @property int $installation_id
 * @property string $owner_login
 * @property string $repository_name
 * @property string $repository_full_name
 * @property ?int $customer_id
 * @property ?int $budget_id
 * @property bool $is_archived
 */
class RepositoryMapping extends Model
{
    protected $table = 'github_repository_mappings';

    protected $fillable = [
        'integration_id',
        'installation_id',
        'owner_login',
        'repository_name',
        'repository_full_name',
        'customer_id',
        'budget_id',
        'is_archived',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'installation_id' => 'int',
            'is_archived' => 'bool',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
