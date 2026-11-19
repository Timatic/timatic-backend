<?php

namespace Timatic\Tempo\DataTransferObjects;

use App\Models\Entry;
use Carbon\CarbonImmutable;

readonly class TempoWorklogPayload
{
    public function __construct(
        public int $issueId,
        public string $authorAccountId,
        public CarbonImmutable $startedAt,
        public int $timeSpentSeconds,
        public string $description,
    ) {}

    public static function fromEntry(Entry $entry, int $issueId, string $authorAccountId): self
    {
        $startedAt = CarbonImmutable::instance($entry->started_at);

        return new self(
            issueId: $issueId,
            authorAccountId: $authorAccountId,
            startedAt: $startedAt,
            timeSpentSeconds: (int) $startedAt->diffInSeconds(CarbonImmutable::instance($entry->ended_at)),
            description: $entry->description ?? $entry->ticket_title ?? '',
        );
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        // Tempo stores worklog times in the instance's timezone, assumed to match Timatic's preferred timezone
        $local = $this->startedAt->setTimezone(config('timatic.preferred_timezone'));

        return [
            'issueId' => $this->issueId,
            'authorAccountId' => $this->authorAccountId,
            'startDate' => $local->format('Y-m-d'),
            'startTime' => $local->format('H:i:s'),
            'timeSpentSeconds' => $this->timeSpentSeconds,
            'description' => $this->description,
        ];
    }
}
