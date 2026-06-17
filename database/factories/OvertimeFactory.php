<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\OvertimeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class OvertimeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'overtime_type_id' => Arr::random([OvertimeType::CUSTOMER, OvertimeType::PERSONAL]),
            'started_at' => $this->faker->dateTime(),
            'ended_at' => $this->faker->dateTime(),
            'exported_at' => $this->faker->dateTime(),
        ];
    }
}
