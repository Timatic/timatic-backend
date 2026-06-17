<?php

namespace Database\Seeders;

use App\Models\OvertimeType;
use Illuminate\Database\Seeder;

class OvertimeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var OvertimeType $overtimeType */
        $overtimeType = OvertimeType::query()->firstOrNew(['title' => 'Personal']);
        $overtimeType->id = OvertimeType::PERSONAL;
        $overtimeType->save();

        $overtimeType = OvertimeType::query()->firstOrNew(['title' => 'Customer']);
        $overtimeType->id = OvertimeType::CUSTOMER;
        $overtimeType->save();
    }
}
