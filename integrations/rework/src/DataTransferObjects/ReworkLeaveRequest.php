<?php

namespace Timatic\Rework\DataTransferObjects;

use Illuminate\Support\Collection;

class ReworkLeaveRequest
{
    /**
     * @param  Collection<int, ReworkLeaveSlot>  $slots
     */
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly string $userId,
        public readonly string $userEmail,
        public readonly Collection $slots,
    ) {}

    /** @return Collection<int, ReworkLeaveSlot> */
    public function slotsForToday(): Collection
    {
        return $this->slots->filter(fn (ReworkLeaveSlot $slot) => $slot->isToday())->values();
    }
}
