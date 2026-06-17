<?php

namespace App\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Includes\IncludeInterface;

/**
 * @implements IncludeInterface<Model>
 */
class CustomRelationInclude implements IncludeInterface
{
    public function __invoke(Builder $query, string $include): void
    {
        // Do nothing - the include will be handled by the JsonApi resource layer
    }
}
