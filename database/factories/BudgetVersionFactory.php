<?php

namespace Database\Factories;

use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initialMinutes = $this->faker->numberBetween(0, 10000);

        return [
            'title' => $this->faker->sentence(4),
            'budget_id' => Budget::factory(),
            'initial_minutes' => $initialMinutes,
            'total_price' => $initialMinutes / 60 * $this->faker->numberBetween(50, 200),
            'effective_from' => Carbon::now()->firstOfMonth(),
            'effective_to' => null,
        ];
    }
}
