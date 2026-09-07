<?php

namespace App\Models;

use App\Events\EventCreated;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property ?int $id
 * @property ?string $user_id
 * @property ?int $budget_id
 * @property ?string $ticket_id
 * @property ?string $source_id
 * @property ?string $external_id
 * @property ?string $ticket_number
 * @property ?string $ticket_type
 * @property ?string $title
 * @property ?string $description
 * @property ?string $customer_id
 * @property ?string $event_type_id
 * @property ?Carbon $started_at
 * @property Carbon $ended_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?bool $is_internal
 * @property Collection<int, Activity> $activities
 * @property ?EventType $eventType
 * @property ?Source $source
 * @property ?Budget $budget
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    private const ESTIMATED_DURATION_MINUTES = 15;

    protected $fillable = [
        'user_id',
        'budget_id',
        'ticket_id',
        'source_id',
        'external_id',
        'ticket_number',
        'ticket_type',
        'title',
        'description',
        'customer_id',
        'event_type_id',
        'started_at',
        'ended_at',
        'is_internal',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => EventCreated::class,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'id' => 'integer',
            'is_internal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsToMany<Activity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<EventType, $this>
     */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    public function effectiveStart(): CarbonInterface
    {
        return $this->started_at ?: $this->ended_at->copy()->subMinutes(self::ESTIMATED_DURATION_MINUTES);
    }
}
