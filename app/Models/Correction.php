<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $id
 * @property ?int $corrected_entry_id
 * @property ?int $correction_entry_id
 * @property ?int $new_entry_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Entry $correctedEntry
 * @property ?Entry $correctionEntry
 * @property ?Entry $newEntry
 */
class Correction extends Model
{
    protected $fillable = [
        'corrected_entry_id',
        'correction_entry_id',
        'new_entry_id',
    ];

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function correctedEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'corrected_entry_id');
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function correctionEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'correction_entry_id');
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function newEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'new_entry_id');
    }
}
