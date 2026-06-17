<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class TextFilterCallback
{
    /**
     * Create a text filter callback for use with AllowedFilter::callback()
     *
     * @param  string  $column  The database column name (e.g., 'user_full_name')
     */
    public static function make(string $column): callable
    {
        return function (Builder $query, mixed $value) use ($column) {
            if (is_string($value)) {
                $query->where($column, '=', $value);

                return;
            }

            // Handle operator-based filtering
            if (is_array($value) && array_key_exists('contains', $value)) {
                $filterValue = $value['contains'];
                $query->where($column, 'LIKE', "%{$filterValue}%");
            }
        };
    }
}
