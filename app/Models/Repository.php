<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\RepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A git repository of an integration, whatever its customer mapping. Holds what Timatic
 * knows about the repository itself; the mapping to a customer stays in the integration.
 *
 * @property int $id
 * @property int $integration_id
 * @property string $identifier
 * @property string $name
 * @property bool $is_ignored
 * @property ?Carbon $last_work_at
 * @property ?Carbon $last_notified_at
 * @property-read Integration $integration
 *
 * @method static Builder<Repository> dueForDigest()
 */
class Repository extends Model
{
    /** @use HasFactory<RepositoryFactory> */
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'identifier',
        'name',
        'is_ignored',
        'last_work_at',
        'last_notified_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_ignored' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_ignored' => 'bool',
            'last_work_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    /**
     * Records work in a repository that has no customer, so the admins hear about it in the digest.
     */
    public static function recordWork(int $integrationId, string $identifier, string $name, CarbonInterface $at): void
    {
        $repository = self::firstOrNew(['integration_id' => $integrationId, 'identifier' => $identifier], ['name' => $name]);

        if ($repository->is_ignored || ($repository->last_work_at !== null && $repository->last_work_at->gte($at))) {
            return;
        }

        $repository->last_work_at = Carbon::instance($at);
        $repository->save();
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Work of the past week, in a repository not mentioned in the past week. Compared by date,
     * so a digest running a few seconds earlier than last week's still mentions it again.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueForDigest(Builder $query): void
    {
        $query->where('is_ignored', false)
            ->where('last_work_at', '>=', now()->subWeek())
            ->where(fn (Builder $query) => $query
                ->whereNull('last_notified_at')
                ->orWhereDate('last_notified_at', '<=', today()->subWeek()));
    }
}
