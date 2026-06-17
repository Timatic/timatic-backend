<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, string|UserFactory>
     */
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->uuid(),
            'name' => $this->faker->company(),
            'account_manager_user_id' => User::factory(),
            'hourly_rate' => number_format(fake()->randomFloat(2, 30, 200), 2),
        ];
    }
}
