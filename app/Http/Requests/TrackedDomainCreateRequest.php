<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\Budget;
use App\Models\TrackedDomain;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $ownUserId = $this->ownUserId();

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
            'data.attributes.budgetId' => ['nullable', 'integer', 'exists:budgets,id'],
            'data.attributes.isInternal' => ['boolean'],
            'data.attributes.isActive' => ['boolean'],
            // A mapping made over the api belongs to the user who made it: tracking somebody else's
            // time is not something a client gets to ask for. Shared mappings are made in the web app,
            // so a token that belongs to nobody gets an empty list and may create no mapping at all.
            'data.attributes.userId' => ['required', 'integer', Rule::in($ownUserId === null ? [] : [$ownUserId])],
            'data.attributes.sourceId' => ['nullable', 'integer', 'exists:tracked_domains,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sourceId = $this->input('data.attributes.sourceId');

                if ($sourceId === null) {
                    return;
                }

                $isShared = TrackedDomain::query()->whereKey($sourceId)->shared()->exists();

                if (! $isShared) {
                    $validator->errors()->add('data.attributes.sourceId', 'A mapping can only be accepted from a shared one.');
                }
            },
            function (Validator $validator): void {
                $budgetId = $this->input('data.attributes.budgetId');
                $customerId = $this->input('data.attributes.customerId');

                if ($budgetId === null || $customerId === null) {
                    return;
                }

                $belongsToCustomer = Budget::query()
                    ->whereKey($budgetId)
                    ->where('customer_id', $customerId)
                    ->exists();

                if (! $belongsToCustomer) {
                    $validator->errors()->add('data.attributes.budgetId', 'The budget does not belong to the customer.');
                }
            },
        ];
    }

    /** The authenticated user, or null for a token that belongs to no one. */
    private function ownUserId(): ?int
    {
        $user = $this->user();

        return $user instanceof User ? $user->id : null;
    }
}
