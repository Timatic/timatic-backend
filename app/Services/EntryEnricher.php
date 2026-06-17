<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MinutesSpentSetOnEntry;
use App\Exceptions\JsonApiException;
use App\Http\Requests\EntryCreateRequest;
use App\Http\Requests\EntryUpdateRequest;
use App\Models\Entry;

class EntryEnricher
{
    public function __construct(
        protected MinutesSpentCalculator $calculator,
        protected OvertimeCreator $overtimeCreator
    ) {}

    public function enrichFromRequest(Entry $entry, EntryCreateRequest|EntryUpdateRequest $request): void
    {
        $hasOvertime = false;
        $hasCustomerOvertime = false;

        if ($request instanceof EntryUpdateRequest && strtolower($request->method()) == 'patch') {
            $hasOvertime = (bool) $entry->personalOvertime;
            $hasCustomerOvertime = (bool) $entry->customerOvertime;

            if ($entry->isDirty(['ended_at', 'started_at']) && $hasOvertime) {
                if (
                    ! $request->input('data.attributes.overtimeStartedAt')
                    || ! $request->input('data.attributes.overtimeEndedAt')
                ) {
                    throw new JsonApiException(422, [
                        [
                            'status' => '422',
                            'detail' => 'Not allowed to change entry startedAt or endedAt with overtime without setting overtimeStartedAt and overtimeEndedAt.',
                        ],
                    ]);
                }
            }
        }

        $this->overtimeCreator->create(
            $entry,
            (bool) $request->input('data.attributes.hasOvertime', $hasOvertime),
            (bool) $request->input('data.attributes.hasCustomerOvertime', $hasCustomerOvertime),
            $request->input('data.attributes.overtimeStartedAt'),
            $request->input('data.attributes.overtimeEndedAt'),
        );

        $entry->unsetRelation('customerOvertime');

        $entry->minutes_spent = $this->calculator->calculate($entry);
        $entry->saveQuietly();

        MinutesSpentSetOnEntry::dispatch($entry);
    }
}
