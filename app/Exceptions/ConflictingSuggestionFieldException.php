<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ConflictingSuggestionFieldException extends Exception
{
    public static function between(string $field, int|string $suggestionValue, int|string $activityValue): self
    {
        return new self("An activity with {$field} [{$activityValue}] can't be added to a suggestion with {$field} [{$suggestionValue}].");
    }
}
