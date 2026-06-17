<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->word(),
            'title' => 'SomeType',
            'is_archived' => false,
            'has_change_ticket' => false,
            'renewal_frequencies' => $this->faker->word(),
        ];
    }
}
