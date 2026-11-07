<?php

declare(strict_types=1);

namespace Timatic\Gmail\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class HistoryPage
{
    private const TRACKED_LABELS = ['SENT', 'DRAFT'];

    /**
     * @param  Collection<int, string>  $messageIds
     */
    public function __construct(
        public string $historyId,
        public Collection $messageIds,
        public ?string $nextPageToken,
    ) {}

    /**
     * Keeps the messages that were written or sent: added as a draft or sent mail, or labelled sent
     * afterwards, which is how a sent draft may show up.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromApiResponse(array $response): self
    {
        /** @var array<int, array<string, array<int, array<string, mixed>>>> $history */
        $history = $response['history'] ?? [];

        $messageIds = collect($history)
            ->flatMap(fn (array $record): array => [
                ...array_map(fn (array $added): array => [$added['message'], $added['message']['labelIds'] ?? []], $record['messagesAdded'] ?? []),
                ...array_map(fn (array $added): array => [$added['message'], $added['labelIds'] ?? []], $record['labelsAdded'] ?? []),
            ])
            ->filter(fn (array $change): bool => array_intersect($change[1], self::TRACKED_LABELS) !== [])
            ->map(fn (array $change): string => $change[0]['id'])
            ->unique()
            ->values();

        return new self(
            historyId: (string) $response['historyId'],
            messageIds: $messageIds,
            nextPageToken: $response['nextPageToken'] ?? null,
        );
    }
}
