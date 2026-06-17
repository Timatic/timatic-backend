<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EventTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->text(8),
            'weight' => 1,
        ];
    }
}
