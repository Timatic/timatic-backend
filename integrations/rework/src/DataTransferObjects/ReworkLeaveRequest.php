<?php

namespace Timatic\Rework\DataTransferObjects;

use Illuminate\Support\Collection;

readonly class ReworkLeaveRequest
{
    /**
     * @param  Collection<int, ReworkLeaveSlot>  $slots
     */
    public function __construct(
        public int $id,
        public string $status,
        public string $userId,
        public string $userEmail,
        public Collection $slots,
    ) {}

    /** @return Collection<int, ReworkLeaveSlot> */
    public function slotsForToday(): Collection
    {
        return $this->slots->filter(fn (ReworkLeaveSlot $slot) => $slot->isToday())->values();
    }
}
