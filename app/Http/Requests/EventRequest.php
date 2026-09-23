<?php

namespace App\Http\Requests;

use App\Http\Requests\Traits\OptionalPatchParameters;
use App\Http\Requests\Traits\ValidatedAttributes;
use App\Models\Budget;
use App\Models\Source;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class EventRequest extends FormRequest
{
    use OptionalPatchParameters;
    use ValidatedAttributes;

    /**
     * An event covers a stretch of work, not a shift. Anything longer is a client that lost track
     * of when its timer started.
     */
    private const MAX_DURATION_HOURS = 24;

    /**
     * Events are recorded on the machine that produced them, so their clock may run slightly ahead
     * of ours. Anything beyond this is not skew but a fabricated timestamp.
     */
    private const CLOCK_SKEW_TOLERANCE_MINUTES = 5;

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->addPatchOptionalValidation([
            'data.type' => ['required', 'in:events'],
            'data.attributes.sourceId' => ['required', 'string', 'exists:'.Source::class.',id'],
            'data.attributes.userId' => [
                'integer',
                'exists:'.User::class.',id',
                'required_without:data.attributes.userExternalId',
            ],
            'data.attributes.userExternalId' => [
                'string',
                'exists:'.User::class.',external_id',
                'required_without:data.attributes.userId',
            ],
            'data.attributes.budgetId' => ['integer', 'exists:'.Budget::class.',id'],
            'data.attributes.ticketId' => ['string'],
            'data.attributes.ticketNumber' => [],
            'data.attributes.ticketType' => [],
            'data.attributes.title' => [],
            'data.attributes.description' => [],
            'data.attributes.customerId' => ['integer', 'exists:customers,id'],
            'data.attributes.customerExternalId' => ['string'],
            'data.attributes.eventTypeId' => ['required', 'string'],
            'data.attributes.startedAt' => $this->startedAtRules(),
            'data.attributes.endedAt' => $this->endedAtRules(),
            'data.attributes.isInternal' => ['boolean'],
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function startedAtRules(): array
    {
        return [
            'bail',
            'required_without:data.attributes.endedAt',
            'date',
            'before_or_equal:'.$this->latestAcceptableTime(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function endedAtRules(): array
    {
        $rules = [
            'bail',
            'required_without:data.attributes.startedAt',
            'date',
            'before_or_equal:'.$this->latestAcceptableTime(),
        ];

        if ($this->input('data.attributes.startedAt') !== null) {
            $rules[] = 'after_or_equal:data.attributes.startedAt';
            $rules[] = $this->withinMaxDuration();
        }

        return $rules;
    }

    private function latestAcceptableTime(): string
    {
        return Carbon::now()->addMinutes(self::CLOCK_SKEW_TOLERANCE_MINUTES)->toDateTimeString();
    }

    private function withinMaxDuration(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $startedAt = $this->input('data.attributes.startedAt');

            if (! is_string($startedAt) || ! is_string($value)) {
                return;
            }

            if (Carbon::parse($startedAt)->diffInHours(Carbon::parse($value)) > self::MAX_DURATION_HOURS) {
                $fail('An event may not span more than '.self::MAX_DURATION_HOURS.' hours.');
            }
        };
    }
}
