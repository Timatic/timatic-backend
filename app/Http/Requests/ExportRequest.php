<?php

namespace App\Http\Requests;

use App\Enums\ExportPeriodOptions;
use App\Integrations\ExportService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
    public function rules(ExportService $exportService): array
    {
        $format = $exportService->findFormat($this->string('exportType')->toString());

        return [
            'exportType' => ['required', 'string', Rule::in($exportService->formatKeys())],
            'year' => ['required', 'integer', 'min:2000', 'max:'.date('Y')],
            'month' => [
                Rule::requiredIf($format?->periodOptions === ExportPeriodOptions::Monthly),
                'nullable', 'integer', 'min:1', 'max:12',
            ],
        ];
    }
}
