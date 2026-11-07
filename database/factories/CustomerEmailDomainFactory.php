<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerEmailDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerEmailDomain>
 */
class CustomerEmailDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => $this->faker->unique()->domainName(),
            'customer_id' => Customer::factory(),
        ];
    }
}
