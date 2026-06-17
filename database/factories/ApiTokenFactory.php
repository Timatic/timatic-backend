<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ApiTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->uuid(),
        ];
    }
}
