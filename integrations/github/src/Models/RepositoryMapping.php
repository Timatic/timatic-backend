<?php

namespace Timatic\GitHub\Models;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
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
 * @property ?int $webhook_id
 * @property-read ?bool $is_ignored only when selected with withIsIgnored()
 * @property-read Integration $integration
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
        'webhook_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'installation_id' => 'int',
            'webhook_id' => 'int',
            'is_ignored' => 'bool',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotExists($this->ignoredRepository());
    }

    /** @param Builder<self> $query */
    public function scopeIgnored(Builder $query): void
    {
        $query->whereExists($this->ignoredRepository());
    }

    /** @param Builder<self> $query */
    public function scopeWithIsIgnored(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        $query->addSelect(['is_ignored' => $this->ignoredRepository()->selectRaw('1')]);
    }

    public function ignore(bool $isIgnored = true): void
    {
        Repository::updateOrCreate(
            ['integration_id' => $this->integration_id, 'identifier' => $this->repository_full_name],
            ['name' => $this->repository_name, 'is_ignored' => $isIgnored],
        );
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

    /** @return Builder<Repository> */
    private function ignoredRepository(): Builder
    {
        return Repository::query()
            ->whereColumn('repositories.integration_id', $this->qualifyColumn('integration_id'))
            ->whereColumn('repositories.identifier', $this->qualifyColumn('repository_full_name'))
            ->where('repositories.is_ignored', true);
    }
}
