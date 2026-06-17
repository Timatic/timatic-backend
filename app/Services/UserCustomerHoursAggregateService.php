<?php

namespace App\Services;

use App\Models\UserCustomerHoursRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserCustomerHoursAggregateService
{
    /**
     * @param  Builder<UserCustomerHoursRecord>|null  $query
     * @return LengthAwarePaginator<int, UserCustomerHoursRecord>
     */
    public function paginatedAggregates(?Builder $query = null): LengthAwarePaginator
    {
        return $this->query($query)
            ->selectRaw('
                customer_id,
                user_id,
                sum(internal_minutes) as internal_minutes,
                sum(budget_minutes) as budget_minutes,
                sum(paid_per_hour_minutes) as paid_per_hour_minutes
            ')
            ->groupBy('customer_id', 'user_id')
            ->jsonPaginate();
    }

    /**
     * @param  Builder<UserCustomerHoursRecord>|null  $query
     */
    public function totalInternalMinutes(?Builder $query = null): int
    {
        return (int) $this->query($query)->sum('internal_minutes');
    }

    /**
     * @param  Builder<UserCustomerHoursRecord>|null  $query
     */
    public function totalBudgetMinutes(?Builder $query = null): int
    {
        return (int) $this->query($query)->sum('budget_minutes');
    }

    /**
     * @param  Builder<UserCustomerHoursRecord>|null  $query
     */
    public function totalPaidPerHourMinutes(?Builder $query = null): int
    {
        return (int) $this->query($query)->sum('paid_per_hour_minutes');
    }

    /**
     * @param  Builder<UserCustomerHoursRecord>|null  $query
     * @return Builder<UserCustomerHoursRecord>
     */
    private function query(?Builder $query = null): Builder
    {
        if (is_null($query)) {
            return UserCustomerHoursRecord::query();
        }

        return $query->clone();
    }
}
