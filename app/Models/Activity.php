<?php

namespace App\Models;

use App\Events\ActivityCreated;
use App\Events\CreatingActivity;
use Carbon\Carbon;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property ?string $source_id
 * @property ?string $user_id
 * @property ?int $budget_id
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?string $ticket_type
 * @property ?string $title
 * @property ?string $description
 * @property ?string $customer_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property ?bool $is_internal
 * @property ?string $event_type_id
 * @property ?int $entry_suggestion_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property Collection<int, Event> $events
 * @property ?EntrySuggestion $entrySuggestion
 * @property ?EventType $eventType
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'started_at',
        'ended_at',
        'event_type_id',
        'customer_id',
        'ticket_id',
        'user_id',
        'description',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ActivityCreated::class,
        'creating' => CreatingActivity::class,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'id' => 'integer',
            'is_internal' => 'bool',
        ];
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return BelongsTo<EntrySuggestion, $this>
     */
    public function entrySuggestion(): BelongsTo
    {
        return $this->belongsTo(EntrySuggestion::class);
    }

    /**
     * @return HasOne<EventType, $this>
     */
    public function eventType(): HasOne
    {
        return $this->hasOne(EventType::class);
    }
}
