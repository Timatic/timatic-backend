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
            'identifier' => $this->faker->unique()->slug(1).'/'.$this->faker->slug(2),
            'is_ignored' => false,
        ];
    }
}
