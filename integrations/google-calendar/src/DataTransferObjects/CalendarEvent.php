<?php

namespace Timatic\GoogleCalendar\DataTransferObjects;

use Carbon\CarbonImmutable;

readonly class CalendarEvent
{
    private const PRIVATE_VISIBILITIES = ['private', 'confidential'];

    public function __construct(
        public string $googleEventId,
        public string $title,
        public ?string $description,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $endedAt,
        public string $visibility = 'default',
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromApiResponse(array $item): self
    {
        $startedAt = CarbonImmutable::parse($item['start']['dateTime'] ?? $item['start']['date'])->utc();
        $endedAt = CarbonImmutable::parse($item['end']['dateTime'] ?? $item['end']['date'])->utc();

        return new self(
            googleEventId: $item['id'],
            title: $item['summary'] ?? '(no title)',
            description: isset($item['description']) ? strip_tags($item['description']) : null,
            startedAt: $startedAt,
            endedAt: $endedAt,
            visibility: $item['visibility'] ?? 'default',
        );
    }

    public function isPrivate(): bool
    {
        return in_array($this->visibility, self::PRIVATE_VISIBILITIES, true);
    }
}
