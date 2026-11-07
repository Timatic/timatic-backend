<?php

declare(strict_types=1);

namespace Timatic\Gmail\DataTransferObjects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class Message
{
    /**
     * @param  array<int, string>  $labelIds
     * @param  Collection<int, string>  $recipients  To first, then Cc, then Bcc
     */
    public function __construct(
        public string $threadId,
        public array $labelIds,
        public ?string $subject,
        public Collection $recipients,
        public CarbonImmutable $writtenAt,
    ) {}

    /** @param array<string, mixed> $item */
    public static function fromApiResponse(array $item): self
    {
        /** @var array<int, array{name: string, value: string}> $headers */
        $headers = $item['payload']['headers'] ?? [];

        $headers = collect($headers)
            ->mapWithKeys(fn (array $header): array => [strtolower($header['name']) => $header['value']]);

        // Matched as bare addresses, because a display name like "Doe, Jane" holds a comma itself.
        $recipients = collect(['to', 'cc', 'bcc'])
            ->flatMap(function (string $name) use ($headers): array {
                preg_match_all('/[^\s<>",;]+@[^\s<>",;]+/', (string) $headers->get($name, ''), $matches);

                return $matches[0];
            })
            ->values();

        return new self(
            threadId: $item['threadId'],
            labelIds: $item['labelIds'] ?? [],
            subject: $headers->get('subject') ?: null,
            recipients: $recipients,
            // Whole seconds, as the event stores them, so a second sync finds the event it made before.
            writtenAt: CarbonImmutable::createFromTimestamp(intdiv((int) $item['internalDate'], 1000)),
        );
    }

    public function isSent(): bool
    {
        return in_array('SENT', $this->labelIds, true);
    }

    public function isDraft(): bool
    {
        return in_array('DRAFT', $this->labelIds, true);
    }
}
