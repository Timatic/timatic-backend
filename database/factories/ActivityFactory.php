<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'started_at' => Carbon::now()->subWeek()->setTime(0, 0, 0),
            'ended_at' => Carbon::now()->subWeek()->setTime(0, 10, 0),
            'user_id' => User::factory(),
            'source_id' => Source::factory(),
        ];
    }
}
