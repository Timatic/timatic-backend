<?php

namespace App\Models;

use App\Events\BudgetSaved;
use Database\Factories\BudgetVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $budget_id
 * @property string $title
 * @property ?string $description
 * @property ?string $change_id
 * @property ?string $contract_id
 * @property ?Carbon $effective_from
 * @property ?Carbon $effective_to
 * @property string $total_price
 * @property int $initial_minutes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property ?Budget $budget
 */
class BudgetVersion extends Model
{
    /** @use HasFactory<BudgetVersionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'budget_id',
        'title',
        'description',
        'change_id',
        'contract_id',
        'effective_from',
        'effective_to',
        'total_price',
        'initial_minutes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (BudgetVersion $budgetVersion) {
            assert(! is_null($budgetVersion->budget));
            BudgetSaved::dispatch($budgetVersion->budget);
        });
    }

    public function isObsoleteComparedTo(self $version): bool
    {
        assert(! is_null($this->effective_from));
        assert(! is_null($version->effective_from));

        return $this->effective_from->greaterThanOrEqualTo($version->effective_from);
    }

    public function overlapsWith(self $version): bool
    {
        assert(! is_null($this->effective_from));
        assert(! is_null($version->effective_from));

        return $this->effective_from->lessThanOrEqualTo($version->effective_from)
            && (
                ! $this->effective_to
                || $this->effective_to->greaterThan($version->effective_from)
            );
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
