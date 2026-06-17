<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;

class BudgetTimeSpentTotalsCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, (\Closure)|string>|string>
     */
    public function rules(): array
    {
        return [
            'periodUnit' => 'required|in:week,year,month',
            'filter.budgetId' => [
                'required',
                'exists:budgets,id',
                function ($attribute, $value, $fail) {
                    /** @var Budget $budget */
                    $budget = Budget::findOrFail($value);
                    if (! is_null($budget->renewal_frequency)) {
                        $fail("The renewal frequency for budget ID $value is not null.");
                    }
                },
            ],
        ];
    }
}
