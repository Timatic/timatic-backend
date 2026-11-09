<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Concerns\ValidatedAttributes;
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
        // A shared mapping is part of the customer's setup, not somebody's own
        $isShared = $this->has('data.attributes.userId') && $this->input('data.attributes.userId') === null;

        return ! $isShared || $this->user()?->can('customers.update') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $ownUserId = $user instanceof User ? $user->id : null;

        return [
            'data.type' => ['required', 'in:trackedDomains'],
            'data.attributes.domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+(\.[a-z0-9-]+)+$/',
                'not_regex:/^www\./',
                Rule::unique(TrackedDomain::class, 'domain')
                    ->where('path', $this->input('data.attributes.path'))
                    ->where('user_id', $this->input('data.attributes.userId')),
            ],
            'data.attributes.path' => ['nullable', 'string', 'max:255', 'regex:#^(/[^?\#\s]*[^/?\#\s])?$#'],
            'data.attributes.customerId' => ['required', 'string', 'exists:customers,id'],
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
            'data.attributes.userId' => [
                'present',
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($ownUserId): void {
                    if ($value !== (string) $ownUserId) {
                        $fail('You can only map a domain for yourself.');
                    }
                },
            ],
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
