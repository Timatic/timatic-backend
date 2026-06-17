<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = Carbon::now()->subDays($this->faker->numberBetween(0, 30));
        $endedAt = $startedAt->clone()->addMinutes($this->faker->numberBetween(15, 300));

        return [
            'ticket_id' => $this->faker->uuid(),
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'description' => $this->faker->text(),
            'is_locked' => false,
            'entry_type' => 'regular',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ];
    }

    public function correction(): Factory
    {
        return $this->state(function (array $attributes) {
            $startedAt = Carbon::now()->subDays($this->faker->numberBetween(0, 30));
            $endedAt = $startedAt->clone()->subMinutes($this->faker->numberBetween(15, 300));

            return [
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'entry_type' => 'correction',
            ];
        });
    }
}
