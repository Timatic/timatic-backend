<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\Budget;
use App\Models\TrackedDomain;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackedDomainCreateRequest extends FormRequest
{
    use ValidatedAttributes;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $ownUserId = $user instanceof User ? $user->id : null;

        return [
            'data.type' => ['required', 'in:tracked-domains'],
            'data.attributes.domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+(\.[a-z0-9-]+)+$/',
                'not_regex:/^www\./',
                Rule::unique(TrackedDomain::class, 'domain')
                    ->where('path', (string) $this->input('data.attributes.path', ''))
                    ->where('user_id', $ownUserId),
            ],
            'data.attributes.path' => ['string', 'max:255', 'regex:#^(/[^?\#\s]*[^/?\#\s])?$#'],
            'data.attributes.customerId' => ['required', 'integer', 'exists:customers,id'],
            'data.attributes.budgetId' => [
                'bail',
                'nullable',
                'integer',
                'exists:budgets,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $customerId = $this->input('data.attributes.customerId');

                    if ($customerId === null) {
                        return;
                    }

                    $belongsToCustomer = Budget::query()
                        ->whereKey($value)
                        ->where('customer_id', $customerId)
                        ->exists();

                    if (! $belongsToCustomer) {
                        $fail('The budget does not belong to the customer.');
                    }
                },
            ],
            'data.attributes.isInternal' => ['boolean'],
            'data.attributes.userId' => ['required', 'integer', Rule::in($ownUserId === null ? [] : [$ownUserId])],
            'data.attributes.parentId' => [
                'bail',
                'nullable',
                'integer',
                'exists:tracked_domains,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! TrackedDomain::query()->whereKey($value)->shared()->exists()) {
                        $fail('A mapping can only be accepted from a shared one.');
                    }
                },
            ],
        ];
    }
}
