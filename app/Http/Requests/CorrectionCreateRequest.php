<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use App\Rules\EntryDidNotCorrectAnotherEntry;
use App\Rules\EntryHasNotBeenCorrected;
use Illuminate\Foundation\Http\FormRequest;

class CorrectionCreateRequest extends FormRequest
{
    use ValidatedAttributes;

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
     * @return array<string, array<EntryDidNotCorrectAnotherEntry|EntryHasNotBeenCorrected|string>>
     */
    public function rules(): array
    {
        return [
            'data.type' => ['required', 'in:corrections'],
            'data.attributes.correctedEntryId' => [
                'required',
                'integer',
                'exists:entries,id',
                new EntryHasNotBeenCorrected,
                new EntryDidNotCorrectAnotherEntry,
            ],
            'data.attributes.newEntryId' => ['integer', 'exists:entries,id'],
        ];
    }
}
