<?php

namespace App\Services;

use App\Models\Entry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class MinutesSpentCalculator
{
    public function calculate(Entry $entry): int
    {
        $totalMinutes = (int) $entry->started_at->diffInMinutes($entry->ended_at, false);

        $actualOvertimeMinutes = 0;
        $calculatedMinutes = BigDecimal::of(0);

        if ($entry->customerOvertime && ! is_null($entry->customerOvertime->percentages)) {
            foreach ((array) $entry->customerOvertime->percentages as $chunk) {
                $actualOvertimeMinutes += $chunk->minutes;

                $chunkCalculatedMinutes = $this->applyPercentage($chunk->minutes, $chunk->percentage);

                $calculatedMinutes = $calculatedMinutes->plus($chunkCalculatedMinutes);
            }
        }

        $calculatedMinutes = $this->addNonOvertimeMinutes($calculatedMinutes, $totalMinutes, $actualOvertimeMinutes);

        return $this->roundToWholeMinutes($calculatedMinutes);
    }

    protected function applyPercentage(int $minutes, int|string $percentage): BigDecimal
    {
        $multiplyValue = BigDecimal::of($percentage)->dividedBy(100, 2);

        return BigDecimal::of($minutes)->multipliedBy($multiplyValue);
    }

    protected function addNonOvertimeMinutes(
        BigDecimal $calculatedMinutes,
        int $totalMinutes,
        int $overtimeMinutes
    ): BigDecimal {
        $nonOvertimeMinutes = $totalMinutes - $overtimeMinutes;

        return $calculatedMinutes->plus($nonOvertimeMinutes);
    }

    /**
     * To round we divide by 1 as this library cannot round without dividing or multiplying.
     */
    protected function roundToWholeMinutes(BigDecimal $calculatedMinutes): int
    {
        return $calculatedMinutes->dividedBy(1, 0, RoundingMode::HALF_UP)->toInt();
    }
}
