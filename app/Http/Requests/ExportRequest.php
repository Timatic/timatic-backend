<?php

namespace App\Http\Requests;

use App\Enums\HoursExportDimension;
use App\Exports\HoursExport;
use App\Integrations\Exports\ExportService;
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
        $monthRangeIsRequired = $format?->periodOptions->monthRangeIsRequired() ?? false;

        return [
            'exportType' => ['required', 'string', Rule::in($exportService->formatKeys())],
            'year' => [
                Rule::requiredIf($format?->periodOptions->yearIsRequired() ?? false),
                'nullable', 'integer', 'min:2000', 'max:'.date('Y'),
            ],
            'month' => [
                Rule::requiredIf($format?->periodOptions->monthIsRequired() ?? false),
                'nullable', 'integer', 'min:1', 'max:12',
            ],
            /** First month (`Y-m`) of an export with period option `month-range`. */
            'fromMonth' => [Rule::requiredIf($monthRangeIsRequired), 'nullable', 'date_format:Y-m'],
            /** Last month (`Y-m`) of an export with period option `month-range`. */
            'toMonth' => [Rule::requiredIf($monthRangeIsRequired), 'nullable', 'date_format:Y-m', 'after_or_equal:fromMonth'],
            /** Rows of the `hours-excel` export, one per value of this dimension. */
            'groupBy' => [Rule::requiredIf($format?->key === HoursExport::KEY), 'nullable', Rule::enum(HoursExportDimension::class)],
            /** Optional second row level of the `hours-excel` export, inside each group. */
            'splitBy' => ['nullable', Rule::enum(HoursExportDimension::class), 'different:groupBy'],
            /** Filters of the `hours-excel` export. */
            'customerId' => ['nullable', 'integer', 'exists:customers,id'],
            'budgetId' => ['nullable', 'integer', 'exists:budgets,id'],
            'userId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
