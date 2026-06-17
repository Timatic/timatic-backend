<?php

namespace App\Models;

use App\Events\EntrySaved;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property ?int $entry_suggestion_id
 * @property string $ticket_id
 * @property string $ticket_number
 * @property string $ticket_title
 * @property string $ticket_type
 * @property string $customer_id
 * @property ?string $customer_name
 * @property ?string $hourly_rate
 * @property ?bool $had_emergency_shift
 * @property ?int $budget_id
 * @property ?int $minutes_spent
 * @property ?int $user_id
 * @property ?string $user_full_name
 * @property ?string $user_email
 * @property ?int $created_by_user_id
 * @property ?string $created_by_user_full_name
 * @property ?string $created_by_user_email
 * @property string $entry_type
 * @property string $description
 * @property bool $is_locked
 * @property bool $is_internal
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property ?Carbon $invoiced_at
 * @property bool $is_invoiced
 * @property Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Budget $budget
 * @property ?Customer $customer
 * @property ?User $user
 * @property Collection|Overtime[] $overtimes
 * @property ?Overtime $personalOvertime
 * @property ?Overtime $customerOvertime
 * @property ?Correction $correctionEntryCorrection
 * @property ?Correction $correctedEntryCorrection
 * @property ?Correction $newEntryCorrection
 *
 * @method static EntryFactory factory(...$parameters)
 *
 * ------------------------------------
 * ONLY IN USERS MONTHLY SUMMARY EXPORT
 * DON'T USE OUTSIDE OF THE EXPORT AND
 * PREFERABLY EVEN REMOVE THESE VALUES
 * IN THE EXPORT
 * ------------------------------------
 *
 * @property ?string $user_team
 * @property ?string $unused_suggestions
 */
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'entry_suggestion_id',
        'ticket_id',
        'ticket_type',
        'ticket_title',
        'ticket_number',
        'customer_id',
        'customer_name',
        'budget_id',
        'user_id',
        'user_email',
        'user_full_name',
        'created_by_user_id',
        'created_by_user_email',
        'created_by_user_full_name',
        'entry_type',
        'description',
        'is_internal',
        'started_at',
        'ended_at',
        'is_invoiced',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saved' => EntrySaved::class,
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'id' => 'integer',
            'budget_id' => 'integer',
            'is_locked' => 'boolean',
            'is_internal' => 'boolean',
            'had_emergency_shift' => 'boolean',
            'is_invoiced' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    #[Scope]
    protected function isInvoiced(Builder $query, bool $isInvoiced): Builder
    {
        if ($isInvoiced) {
            return $query->whereNotNull('invoiced_at');
        } else {
            return $query->whereNull('invoiced_at');
        }
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    #[Scope]
    protected function settlement(Builder $query, string $settlement): Builder
    {
        assert($settlement == 'internal' || $settlement == 'budget' || $settlement == 'paid-per-hour');

        match ($settlement) {
            'internal' => $query->where('is_internal', '=', 1),
            'budget' => $query->whereNotNull('budget_id'),
            'paid-per-hour' => $query->where(
                function ($query) {
                    $query->where('is_internal', '=', 0);
                    $query->whereNull('budget_id');
                }
            )
        };

        return $query;
    }

    /**
     * @param  Builder<Entry>  $query
     * @return Builder<Entry>
     */
    #[Scope]
    protected function isInvoiceable(Builder $query, bool $isInvoiceable): Builder
    {
        if ($isInvoiceable) {
            return $query->where(function ($query) {
                $query->where('is_internal', 0)
                    ->whereNull('budget_id');
            });
        } else {
            return $query->where(function ($query) {
                $query->where('is_internal', 1)
                    ->orWhereNotNull('budget_id');
            });
        }
    }

    public function setIsInvoicedAttribute(?bool $isInvoiced): void
    {
        if ($isInvoiced) {
            $this->invoiced_at = now();
        }
    }

    public function getIsInvoicedAttribute(): bool
    {
        return ! is_null($this->invoiced_at);
    }

    public function setUserEmailAttribute(?string $email): void
    {
        $this->attributes['user_email'] = null;

        if (! empty($email)) {
            $this->attributes['user_email'] = strtolower($email);
        }
    }

    public function setUserFullNameAttribute(?string $fullName): void
    {
        $this->attributes['user_full_name'] = null;

        if (! empty(trim((string) $fullName))) {
            $this->attributes['user_full_name'] = trim((string) $fullName);
        }
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasOne<Correction, $this>
     */
    public function correctionEntryCorrection(): HasOne
    {
        return $this->hasOne(Correction::class, 'correction_entry_id');
    }

    /**
     * @return HasOne<Correction, $this>
     */
    public function correctedEntryCorrection(): HasOne
    {
        return $this->hasOne(Correction::class, 'corrected_entry_id');
    }

    /**
     * @return HasOne<Correction, $this>
     */
    public function newEntryCorrection(): HasOne
    {
        return $this->hasOne(Correction::class, 'new_entry_id');
    }

    /**
     * @return HasMany<Overtime, $this>
     */
    public function overtimes(): HasMany
    {
        return $this->hasMany(Overtime::class);
    }

    /**
     * @return HasOne<Overtime, $this>
     */
    public function personalOvertime(): HasOne
    {
        return $this->hasOne(Overtime::class)->where('overtime_type_id', OvertimeType::PERSONAL);
    }

    /**
     * @return HasOne<Overtime, $this>
     */
    public function customerOvertime(): HasOne
    {
        return $this->hasOne(Overtime::class)->where('overtime_type_id', OvertimeType::CUSTOMER);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getHourlyRateBigDecimal(): BigDecimal
    {
        if ($this->budget) {
            return $this->budget->getHourlyRateBigDecimal($this->started_at);
        }

        return BigDecimal::of($this->attributes['hourly_rate'] ?? 0);
    }
}
