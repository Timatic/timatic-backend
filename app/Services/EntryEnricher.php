<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MinutesSpentSetOnEntry;
use App\Exceptions\JsonApiException;
use App\Models\Entry;

class EntryEnricher
{
    public function __construct(
        protected MinutesSpentCalculator $calculator,
        protected OvertimeCreator $overtimeCreator
    ) {}

    public function enrich(
        Entry $entry,
        bool $isPartialUpdate,
        bool $hasOvertime,
        bool $hasCustomerOvertime,
        ?string $overtimeStartedAt,
        ?string $overtimeEndedAt,
    ): void {
        if ($isPartialUpdate && $entry->isDirty(['ended_at', 'started_at']) && $entry->personalOvertime) {
            if (! $overtimeStartedAt || ! $overtimeEndedAt) {
                throw new JsonApiException(422, [
                    [
                        'status' => '422',
                        'detail' => 'Not allowed to change entry startedAt or endedAt with overtime without setting overtimeStartedAt and overtimeEndedAt.',
                    ],
                ]);
            }
        }

        $this->overtimeCreator->create(
            $entry,
            $hasOvertime,
            $hasCustomerOvertime,
            $overtimeStartedAt,
            $overtimeEndedAt,
        );

        $entry->unsetRelation('customerOvertime');

        $entry->minutes_spent = $this->calculator->calculate($entry);
        $entry->saveQuietly();

        MinutesSpentSetOnEntry::dispatch($entry);
    }
}
