<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\OvertimeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use stdClass;

/**
 * @property int $id
 * @property int $entry_id
 * @property ?string $overtime_type_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property stdClass $percentages
 * @property Carbon $approved_at
 * @property ?int $approved_by_user_id
 * @property Carbon $exported_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property OvertimeType $type
 * @property ?Entry $entry
 */
class Overtime extends Model
{
    /** @use HasFactory<OvertimeFactory> */
    use HasFactory;

    protected $fillable = [
        'entry_id',
        'overtime_type_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function approve(int $userId): void
    {
        $this->approved_by_user_id = $userId;
        $this->approved_at = Carbon::now();
        $this->save();
    }

    /**
     * @param  Builder<Overtime>  $query
     * @return Builder<Overtime>
     */
    #[Scope]
    protected function isApproved(Builder $query, bool $isApproved): Builder
    {
        if ($isApproved) {
            return $query->whereNotNull('approved_at');
        } else {
            return $query->whereNull('approved_at');
        }
    }

    /**
     * @param  Builder<Overtime>  $query
     * @return Builder<Overtime>
     */
    #[Scope]
    protected function isExported(Builder $query, bool $isExported): Builder
    {
        if ($isExported) {
            return $query->whereNotNull('exported_at');
        } else {
            return $query->whereNull('exported_at');
        }
    }

    public function getPercentagesAttribute(): ?stdClass
    {
        if (is_null($this->attributes['percentages'])) {
            return null;
        }

        return json_decode($this->attributes['percentages']);
    }

    /**
     * @param  null|array<string, string>|stdClass  $percentages
     */
    public function setPercentagesAttribute(array|stdClass|null $percentages): void
    {
        if (is_null($percentages)) {
            $this->attributes['percentages'] = null;

            return;
        }

        $this->attributes['percentages'] = json_encode($percentages);
    }

    /**
     * @return BelongsTo<OvertimeType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(OvertimeType::class);
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
