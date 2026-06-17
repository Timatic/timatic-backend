<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class Period
{
    public Budget $budget;

    protected Carbon $startDate;

    protected Carbon $endDate;

    protected int $spentMinutes;

    protected int $ticketCount;

    protected ?Carbon $timeTravelDate;

    protected BudgetVersion $activeVersion;

    protected bool $isFirstPeriod = false;

    protected bool $isLastPeriod = false;

    public static function create(): self
    {
        return new self;
    }

    /**
     * @return array<string>
     */
    public function getFillable(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getDates(): array
    {
        return ['startDate', 'endDate'];
    }

    public function getId(): string
    {
        return sha1($this->budget->id.$this->startDate->toDateString().$this->endDate->toDateString());
    }

    public function setBudget(Budget $budget): self
    {
        $this->budget = $budget;

        return $this;
    }

    public function setStartDate(Carbon $startDate): self
    {
        $this->startDate = $startDate;
        $this->activeVersion = $this->budget->activeVersion($this->startDate);

        return $this;
    }

    public function setEndDate(Carbon $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStartDate(): Carbon
    {
        return $this->startDate;
    }

    public function getEndDate(): Carbon
    {
        return $this->endDate;
    }

    public function getTitle(): string
    {
        return $this->activeVersion->title;
    }

    public function getDescription(): ?string
    {
        return $this->activeVersion->description;
    }

    public function getTotalPrice(): string
    {
        return $this->activeVersion->total_price;
    }

    public function getInitialMinutes(): int
    {
        return $this->activeVersion->initial_minutes;
    }

    public function getRemainingMinutes(bool $allowNegative = false): int
    {
        $minutes = $this->activeVersion->initial_minutes - $this->getSpentMinutes();
        if ($allowNegative == false && $minutes < 0) {
            return 0;
        } else {
            return $minutes;
        }
    }

    public function getRemainingHours(bool $allowNegative = false): BigDecimal
    {
        return BigDecimal::of($this->getRemainingMinutes($allowNegative))
            ->dividedBy(60, 4, RoundingMode::HALF_UP);
    }

    protected function setAggregates(): void
    {
        $query = DB::table('entries')
            ->selectRaw('count(distinct ticket_number) as ticket_count, sum(minutes_spent) as minutes_spent')
            ->where('budget_id', '=', $this->budget->id)
            ->where('is_internal', '=', 0)
            ->whereNull('deleted_at');

        if (! $this->isFirstPeriod) {
            $query->where('started_at', '>=', $this->startDate);
        }
        if (! $this->isLastPeriod) {
            $query->where('started_at', '<=', $this->endDate);
        }

        if (isset($this->timeTravelDate)) {
            $query->where('started_at', '<', $this->timeTravelDate->clone());
        }

        /** @var stdClass $results */
        $results = $query->first();

        $this->spentMinutes = $results->minutes_spent ?? 0;
        $this->ticketCount = $results->ticket_count ?? 0;
    }

    public function getSpentMinutes(): int
    {
        if (! isset($this->spentMinutes)) {
            $this->setAggregates();
        }

        return $this->spentMinutes;
    }

    public function getTicketCount(): int
    {
        if (! isset($this->ticketCount)) {
            $this->setAggregates();
        }

        return $this->ticketCount;
    }

    public function getRemainingCredit(bool $allowNegative = false): BigDecimal
    {
        return $this->getRemainingHours($allowNegative)
            ->multipliedBy($this->budget->getHourlyRateBigDecimal($this->startDate))
            ->toScale(2, RoundingMode::HALF_UP);
    }

    public function travelTo(Carbon $date): self
    {
        $period = new self;
        $period->setBudget($this->budget);
        $period->setStartDate($this->startDate);
        $period->setEndDate($this->endDate);
        $period->setIsFirstPeriod($this->isFirstPeriod);
        $period->setIsLastPeriod($this->isLastPeriod);
        $period->timeTravelDate = $date;

        return $period;
    }

    public function setIsFirstPeriod(bool $isFirstPeriod): self
    {
        $this->isFirstPeriod = $isFirstPeriod;

        return $this;
    }

    public function setIsLastPeriod(bool $isLastPeriod): self
    {
        $this->isLastPeriod = $isLastPeriod;

        return $this;
    }
}
