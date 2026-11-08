<?php

namespace Timatic\Bitbucket\Models;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $integration_id
 * @property string $workspace_slug
 * @property string $repository_slug
 * @property string $repository_name
 * @property ?int $customer_id
 * @property ?int $budget_id
 * @property-read ?bool $is_ignored only when selected with withIsIgnored()
 */
class RepositoryMapping extends Model
{
    protected $table = 'bitbucket_repository_mappings';

    protected $fillable = [
        'integration_id',
        'workspace_slug',
        'repository_slug',
        'repository_name',
        'customer_id',
        'budget_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_ignored' => 'bool'];
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

    public function ignore(): void
    {
        Repository::updateOrCreate(['integration_id' => $this->integration_id, 'identifier' => $this->fullName()], ['is_ignored' => true]);
    }

    public function stopIgnoring(): void
    {
        Repository::where(['integration_id' => $this->integration_id, 'identifier' => $this->fullName()])->update(['is_ignored' => false]);
    }

    public function fullName(): string
    {
        return $this->workspace_slug.'/'.$this->repository_slug;
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
            ->whereRaw("repositories.identifier = concat({$this->qualifyColumn('workspace_slug')}, '/', {$this->qualifyColumn('repository_slug')})")
            ->where('repositories.is_ignored', true);
    }
}
