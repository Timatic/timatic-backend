<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\OptionalPatchParameters;
use App\Http\Requests\Traits\ValidatedAttributes;
use Illuminate\Foundation\Http\FormRequest;

class CorrectionUpdateRequest extends FormRequest
{
    use OptionalPatchParameters;
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
     * @return array<string, string>
     */
    public function rules(): array
    {
        return $this->addPatchOptionalValidation([
            'data.type' => ['required', 'in:corrections'],
            'data.attributes.newEntryId' => ['integer', 'exists:entries,id'],
        ]);
    }
}
