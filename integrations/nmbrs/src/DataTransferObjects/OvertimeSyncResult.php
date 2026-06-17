<?php

namespace Timatic\Nmbrs\DataTransferObjects;

readonly class OvertimeSyncResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $exportedCount,
        public array $warnings,
    ) {}

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
