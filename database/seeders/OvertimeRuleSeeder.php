<?php

namespace Database\Seeders;

use App\Models\OvertimeRule;
use Illuminate\Database\Seeder;

class OvertimeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'key' => 'weekday',
                'start_time' => '08:00',
                'end_time' => '18:00',
                'days' => [1, 2, 3, 4, 5],
                'percentage' => '100',
                'sort_order' => 1,
            ],
            [
                'key' => 'evening',
                'start_time' => '18:00',
                'end_time' => '00:00',
                'days' => [1, 2, 3, 4, 5],
                'percentage' => '135',
                'sort_order' => 2,
            ],
            [
                'key' => 'night',
                'start_time' => '00:00',
                'end_time' => '08:00',
                'days' => [1, 2, 3, 4, 5],
                'percentage' => '150',
                'sort_order' => 3,
            ],
            [
                'key' => 'weekend',
                'start_time' => '00:00',
                'end_time' => '00:00',
                'days' => [6, 7],
                'percentage' => '150',
                'sort_order' => 4,
            ],
            [
                'key' => 'holiday',
                'start_time' => '00:00',
                'end_time' => '00:00',
                'days' => ['holiday'],
                'percentage' => '150',
                'sort_order' => 5,
            ],
        ];

        foreach ($rules as $rule) {
            OvertimeRule::query()->firstOrCreate(['key' => $rule['key']], $rule);
        }
    }
}
