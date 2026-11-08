<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'identifier' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'is_ignored' => false,
        ];
    }
}
