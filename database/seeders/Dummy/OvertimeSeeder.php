<?php

namespace Database\Seeders\Dummy;

use App\Models\Entry;
use App\Services\OvertimeCreator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OvertimeSeeder extends Seeder
{
    public function run(): void
    {
        $overtimeCreator = app(OvertimeCreator::class);

        $startDate = Carbon::createFromDate(2025, 1, 1);
        $endDate = Carbon::now();
        $currentMonth = $startDate->copy();

        while ($currentMonth->lte($endDate)) {
            $entries = Entry::query()
                ->whereMonth('started_at', $currentMonth->month)
                ->whereYear('started_at', $currentMonth->year)
                ->whereDoesntHave('personalOvertime')
                ->inRandomOrder()
                ->limit(rand(2, 5))
                ->get();

            foreach ($entries as $entry) {
                $overtimeCreator->create(
                    entry: $entry,
                    hasOvertime: true,
                    hasCustomerOvertime: false
                );
            }

            $currentMonth->addMonth();
        }
    }
}
