<?php

namespace Database\Factories;

use App\Models\EventType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'user_id' => User::factory(),
            'ticket_id' => $this->faker->uuid(),
            'ticket_number' => 'IMX'.$this->faker->numerify('########'),
            'ticket_type' => 'incident',
            'event_type_id' => EventType::factory(),
            'ended_at' => $this->faker->dateTimeBetween('-1 week'),
        ];
    }
}
