<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $start_time
 * @property string $end_time
 * @property array<int, int|string> $days
 * @property string $percentage
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OvertimeRule extends Model
{
    protected $fillable = [
        'key',
        'start_time',
        'end_time',
        'days',
        'percentage',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<OvertimeRule> $query */
    public function scopeByPriority(Builder $query): void
    {
        $query->orderByDesc('sort_order');
    }
}
