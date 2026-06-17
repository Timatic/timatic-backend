<?php

namespace Timatic\Rework\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Timatic\Rework\DataTransferObjects\ReworkLeaveRequest;
use Timatic\Rework\DataTransferObjects\ReworkLeaveSlot;

class GetLeaveRequestsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/leave/requests';
    }

    protected function defaultQuery(): array
    {
        return [
            'status' => 'ok',
        ];
    }

    /** @return Collection<int, ReworkLeaveRequest> */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var array<mixed> $items */
        $items = $response->json();

        return collect($items)
            ->map(fn (mixed $item): ReworkLeaveRequest => new ReworkLeaveRequest(
                id: (int) $item['id'],
                status: (string) ($item['status'] ?? ''),
                userId: (string) ($item['user']['id'] ?? ''),
                userEmail: strtolower((string) $item['user']['email']),
                slots: $this->parseSlots($item['slots'] ?? []),
            ))
            ->values();
    }

    /** @return Collection<int, ReworkLeaveSlot> */
    private function parseSlots(mixed $slots): Collection
    {
        if (! is_array($slots)) {
            return collect();
        }

        return collect($slots)
            ->map(fn (mixed $slot): ReworkLeaveSlot => new ReworkLeaveSlot(
                id: (int) $slot['id'],
                date: CarbonImmutable::parse($slot['date'], 'Europe/Amsterdam')->startOfDay(),
                hours: (float) ($slot['hours'] ?? 0),
                allDay: (bool) ($slot['all_day'] ?? false),
            ))
            ->values();
    }
}
