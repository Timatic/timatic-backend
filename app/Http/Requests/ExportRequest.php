<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exportType' => 'required|string',
            'year' => 'required|integer|min:2000|max:'.date('Y'),
            'month' => 'required_unless:exportType,entries-excel|nullable|integer|min:1|max:12',
        ];
    }
}
