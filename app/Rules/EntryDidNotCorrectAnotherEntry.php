<?php

namespace App\Rules;

use App\Models\Correction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EntryDidNotCorrectAnotherEntry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = Correction::query()
            ->where('correction_entry_id', $value)
            ->count();

        if ($count !== 0) {
            $fail('Cannot correct an entry that was used to correct another entry.');
        }
    }
}
