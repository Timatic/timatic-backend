<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class DateFilterCallback
{
    /**
     * Supported operator mappings
     */
    private const OPERATORS = [
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
    ];

    /**
     * Create a date filter callback for use with AllowedFilter::callback()
     *
     * @param  string  $column  The database column name (e.g., 'started_at')
     */
    public static function make(string $column): callable
    {
        return function (Builder $query, mixed $value) use ($column) {
            // Handle operator-based filtering
            if (is_array($value)) {
                foreach ($value as $operator => $filterValue) {
                    if (! isset(self::OPERATORS[$operator])) {
                        // Skip unknown operators to avoid SQL errors
                        continue;
                    }

                    $sqlOperator = self::OPERATORS[$operator];
                    $query->where($column, $sqlOperator, $filterValue);
                }
            }
        };
    }
}
