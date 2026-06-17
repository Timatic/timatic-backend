<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeSpentTotalsCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'filter.startedAt.lte' => 'required',
            'filter.startedAt.gte' => 'required',
            'periodUnit' => 'required|in:week,year,month',
            'filter.teamId' => 'exists:teams,id',
            'filter.userId' => 'exists:users,id',
        ];
    }
}
