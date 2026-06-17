<?php

namespace App\Rules;

use App\Models\Correction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EntryHasNotBeenCorrected implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = Correction::query()
            ->where('corrected_entry_id', $value)
            ->count();

        if ($count !== 0) {
            $fail('Cannot correct an entry that has already been corrected.');
        }
    }
}
