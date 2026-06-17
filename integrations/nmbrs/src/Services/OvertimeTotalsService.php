<?php

namespace Timatic\Nmbrs\Services;

use App\Models\Overtime;
use Illuminate\Support\Collection;

class OvertimeTotalsService
{
    /**
     * @param  Collection<int, Overtime>  $overtimes
     * @return array<int, int>
     */
    public function sumPerPercentage(Collection $overtimes): array
    {
        $percentages = [];

        foreach ($overtimes as $overtime) {
            foreach ((array) $overtime->percentages as $percentageData) {
                $percentage = (int) $percentageData->percentage;
                $percentages[$percentage] = ($percentages[$percentage] ?? 0) + (int) $percentageData->minutes;
            }
        }

        return $percentages;
    }
}
