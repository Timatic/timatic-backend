<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntrySuggestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => $this->faker->word(),
            'ticket_number' => $this->faker->word(),
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'ticket_type' => $this->faker->word(),
            'is_internal' => $this->faker->boolean(),
        ];
    }
}
