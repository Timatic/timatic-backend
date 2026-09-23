<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\TrackedDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackedDomain>
 */
class TrackedDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => $this->faker->unique()->domainName(),
            'path' => '',
            'customer_id' => Customer::factory(),
            'budget_id' => null,
            'is_internal' => false,
        ];
    }
}
