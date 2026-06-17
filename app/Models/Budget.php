<?php

namespace App\Models;

use App\Events\BudgetSaved;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $customer_id
 * @property bool $show_to_customer
 * @property string $budget_type_id
 * @property ?Carbon $started_at
 * @property ?Carbon $ended_at
 * @property ?string $renewal_frequency
 * @property ?Carbon $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?int $supervisor_user_id
 * @property Collection|Entry[] $entries
 * @property Collection|BudgetVersion[] $budgetVersions
 * @property BudgetType $budgetType
 * @property ?Customer $customer
 * @property ?User $supervisor
 * @property Collection<int, User> $allowedUsers
 *
 * @method static Builder|Budget hasMinutesAndPrice(Carbon $date)
 * @method static Builder|Budget isArchived(bool $isArchived)
 * @method static Builder|Budget query()
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @var array<string, bool>
     */
    protected $attributes = [
        'show_to_customer' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'budget_type_id',
        'customer_id',
        'show_to_customer',
        'started_at',
        'ended_at',
        'renewal_frequency',
        'archived_at',
        'supervisor_user_id',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saved' => BudgetSaved::class,
    ];

    /**
     * @var array|string[]
     */
    protected array $immutable = [
        'budget_type_id',
        'customer_id',
        'renewal_frequency',
        'started_at',
    ];

    protected $with = ['budgetType', 'budgetVersions'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'archived_at' => 'datetime',
            'id' => 'integer',
            'show_to_customer' => 'bool',
        ];
    }

    /**
     * @param  Builder<Budget>  $query
     * @return Builder<Budget>
     */
    #[Scope]
    protected function showToCustomer(Builder $query, bool $showToCustomer): Builder
    {
        return $query->where('show_to_customer', $showToCustomer);
    }

    /**
     * @param  Builder<Budget>  $query
     * @return Builder<Budget>
     */
    #[Scope]
    protected function isArchived(Builder $query, bool $isArchived): Builder
    {
        if ($isArchived) {
            return $query->whereNotNull('archived_at');
        } else {
            return $query->whereNull('archived_at');
        }
    }

    /**
     * @param  Builder<Budget>  $query
     */
    #[Scope]
    protected function externalId(Builder $query, string $externalId): void
    {
        $query->whereHas('customer', function (Builder $query) use ($externalId) {
            $query->where('external_id', $externalId);
        });
    }

    /**
     * @return \Illuminate\Support\Collection|Period[]
     */
    public function periods(): \Illuminate\Support\Collection
    {
        if ($this->relationLoaded('periods')) {
            return $this->getRelation('periods');
        }

        if (is_null($this->renewal_frequency)) {
            $periods = $this->createPeriodsForOneTimeBudget();
        } else {
            $periods = $this->createPeriodsForRenewingBudget();
        }

        $this->setRelation('periods', $periods);

        return $periods;
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->immutable) && $this->exists) {
            return;
        }

        parent::setAttribute($key, $value);
    }

    private function createPeriodsForOneTimeBudget(): \Illuminate\Support\Collection
    {
        $periods = new \Illuminate\Support\Collection;

        assert(! is_null($this->started_at));
        assert(! is_null($this->ended_at));

        $endDate = $this->ended_at;
        if ($this->archived_at) {
            $endDate = $this->archived_at->isBefore($endDate) ? $this->archived_at : $endDate;
        }
        $periods->push(
            Period::create()
                ->setBudget($this)
                ->setStartDate($this->started_at)
                ->setEndDate($endDate)
                ->setIsFirstPeriod(true)
                ->setIsLastPeriod(true)
        );

        return $periods;
    }

    private function createPeriodsForRenewingBudget(): \Illuminate\Support\Collection
    {
        /** @var \Illuminate\Support\Collection<int, Period> $periods */
        $periods = new \Illuminate\Support\Collection;

        assert(! is_null($this->started_at));

        $frequency = ($this->renewal_frequency == 'yearly' ? 'year' : 'month');
        $startDate = $this->started_at->clone();
        $maxPeriodEndDate = Carbon::now()->add($frequency, 1);
        $endDate = $this->ended_at;
        if ($this->archived_at) {
            $endDate = $this->archived_at;
        }

        assert($endDate instanceof Carbon);

        $alignToMonthStart = config('timatic.feature.align_periods_to_month_start') && $frequency === 'month';
        $isFirstPeriod = true;

        do {
            $startDate = $startDate->setTimezone(config('timatic.preferred_timezone'))->startOfDay();

            if ($alignToMonthStart && ! $isFirstPeriod) {
                $startDate = $startDate->startOfMonth();
            }

            $isPartialFirstPeriod = $alignToMonthStart && $isFirstPeriod && $startDate->day !== 1;

            $periodEnd = $isPartialFirstPeriod
                ? $startDate->clone()->startOfMonth()->addMonth()->subSecond()
                : $startDate->clone()->add($frequency, 1)->subSecond();

            $periods->push(
                Period::create()
                    ->setBudget($this)
                    ->setStartDate($startDate->clone())
                    ->setEndDate($periodEnd->isBefore($endDate) ? $periodEnd : $endDate)
            );

            $startDate->add($frequency, 1);
            $isFirstPeriod = false;
        } while ($startDate->isBefore($endDate) && $startDate->isBefore($maxPeriodEndDate));

        if ($periods->count() >= 1) {
            if (! is_null($periods->first())) {
                $periods->first()->setIsFirstPeriod(true);
            }
            if (! is_null($periods->last())) {
                $periods->last()->setIsLastPeriod(true);
            }
        }

        if ($this->archived_at) {
            if (! is_null($periods->last())) {
                $periods->last()->setEndDate($this->archived_at);
            }
        }

        return $periods;
    }

    public function getPeriodAt(?Carbon $date): ?Period
    {
        if (is_null($date)) {
            return null;
        }

        foreach ($this->periods() as $period) {
            if (
                $period->getStartDate()->lessThanOrEqualTo($date) &&
                $period->getEndDate()->greaterThanOrEqualTo($date)
            ) {
                return $period;
            }
        }

        return null;
    }

    public function getCurrentPeriodRelationData(): ?Period
    {
        return $this->getPeriodAt(Carbon::now());
    }

    public function getLastPeriodRelationData(): ?Period
    {
        assert(! is_null($this->ended_at));

        if ($this->ended_at < Carbon::today()) {
            return $this->getPeriodAt($this->ended_at->clone()->subDay());
        } else {
            return $this->getCurrentPeriodRelationData();
        }
    }

    /**
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<BudgetVersion, $this>
     */
    public function budgetVersions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class)
            ->orderBy('effective_from', 'desc');
    }

    /**
     * @return BelongsTo<BudgetType, $this>
     */
    public function budgetType(): BelongsTo
    {
        return $this->belongsTo(BudgetType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function allowedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function getTitle(?Carbon $timestamp = null): string
    {
        $version = $this->activeVersion($timestamp);

        return $version->title;
    }

    public function getHourlyRateBigDecimal(?Carbon $timestamp = null): BigDecimal
    {
        $version = $this->activeVersion($timestamp);

        $hours = BigDecimal::of($version->initial_minutes)
            ->dividedBy(60, 4, RoundingMode::HALF_UP);

        if ($hours->isEqualTo(0)) {
            return BigDecimal::of(0);
        }

        return BigDecimal::of($version->total_price)->dividedBy($hours, 4, RoundingMode::HALF_UP);
    }

    public function activeVersion(?Carbon $timestamp = null): BudgetVersion
    {
        if (is_null($timestamp)) {
            $timestamp = Carbon::now();
        }

        assert(! is_null($this->started_at));

        // entries can be moved to a budget that is created later than the entries themselves
        if ($timestamp->isBefore($this->started_at)) {
            $timestamp = $this->started_at;
        }

        foreach ($this->budgetVersions as $budgetVersion) {
            assert(! is_null($budgetVersion->effective_from));

            if (
                $timestamp->greaterThanOrEqualTo($budgetVersion->effective_from) &&
                (is_null($budgetVersion->effective_to) || $timestamp->lessThanOrEqualTo($budgetVersion->effective_to))
            ) {
                return $budgetVersion;
            }
        }

        $version = $this->budgetVersions->whereNull('effective_to')->first();

        assert($version instanceof BudgetVersion);

        return $version;
    }

    public function getTotalPrice(?Carbon $timestamp = null): string
    {
        $version = $this->activeVersion($timestamp);

        return $version->total_price;
    }
}
