<?php

namespace App\Models;

use App\Exceptions\ConflictingSuggestionFieldException;
use Carbon\Carbon;
use Database\Factories\EntrySuggestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property ?int $id
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?string $customer_id
 * @property ?string $user_id
 * @property ?string $date
 * @property ?string $ticket_title
 * @property ?string $ticket_type
 * @property ?string $customer_name
 * @property ?int $budget_id
 * @property ?bool $is_internal
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?User $user
 * @property Collection<int, Activity> $activities
 * @property ?Entry $entry
 */
class EntrySuggestion extends Model
{
    /** @use HasFactory<EntrySuggestionFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    public const RETENTION_DAYS = 30;

    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'customer_id',
        'user_id',
        'date',
        'ticket_title',
        'ticket_type',
        'customer_name',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'is_internal' => 'bool',
        ];
    }

    /**
     * Open suggestions outlive their pruned activities as empty shells. Dismissed
     * ones are kept because they must keep suppressing on a rebuild of their day,
     * and booked ones are part of their entry's history.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        // The prune command includes trashed models, so dismissed ones are excluded explicitly.
        return $this->newQuery()
            ->whereNull('deleted_at')
            ->whereDoesntHave('entry', fn (Builder $query) => $query->withTrashed())
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Fields still unknown are taken from the activity, so the suggestion
     * becomes more specific with every activity added.
     *
     * @throws ConflictingSuggestionFieldException
     */
    public function addActivity(Activity $activity): void
    {
        foreach (['customer_id', 'budget_id', 'ticket_number'] as $definingField) {
            if ($this->{$definingField} !== null && $activity->{$definingField} !== null && $this->{$definingField} !== $activity->{$definingField}) {
                throw ConflictingSuggestionFieldException::between($definingField, $this->{$definingField}, $activity->{$definingField});
            }
        }

        $activities = $this->relationLoaded('activities') ? $this->activities : collect();
        $this->setRelation('activities', $activities->push($activity)->sortBy('started_at')->values());

        $this->user_id ??= $activity->user_id;
        $this->is_internal ??= $activity->is_internal;
        $this->customer_id ??= $activity->customer_id;
        $this->budget_id ??= $activity->budget_id;

        $this->ticket_id ??= $activity->ticket_id;
        $this->ticket_number ??= $activity->ticket_number;
        $this->ticket_type ??= $activity->ticket_type;
    }

    /**
     * Overlapping activities are counted once, so their intervals are merged before summing.
     */
    public function minutesSpent(): int
    {
        $seconds = 0;
        $coveredUntil = null;

        foreach ($this->activities->sortBy('started_at') as $activity) {
            $start = $coveredUntil?->max($activity->started_at) ?? $activity->started_at;

            if ($activity->ended_at->greaterThan($start)) {
                $seconds += $start->diffInSeconds($activity->ended_at);
                $coveredUntil = $activity->ended_at;
            }
        }

        return (int) round($seconds / 60);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasOne<Entry, $this>
     */
    public function entry(): HasOne
    {
        return $this->hasOne(Entry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
