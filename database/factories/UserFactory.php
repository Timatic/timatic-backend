<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, string|TeamFactory>
     */
    public function definition(): array
    {
        return [
            'external_id' => $this->faker->uuid(),
            'team_id' => Team::factory(),
            'email' => $this->faker->email(),
            'given_name' => $this->faker->firstName(),
            'family_name' => $this->faker->lastName(),
        ];
    }
}
