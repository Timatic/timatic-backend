<?php

namespace Database\Factories;

use App\Models\BudgetType;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class BudgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var BudgetType $budgetType */
        $budgetType = BudgetType::all()->random();

        return [
            'budget_type_id' => $budgetType->id,
            'customer_id' => Customer::factory(),
            'show_to_customer' => true,
            'started_at' => Carbon::now(timezone: config('timatic.preferred_timezone'))->startOfMonth()->subMonth()->utc(),
            'ended_at' => Carbon::now(timezone: config('timatic.preferred_timezone'))->startOfMonth()->addYear()->utc(),
            'archived_at' => null,
            'renewal_frequency' => Arr::random(['monthly', 'yearly', null]),
            'supervisor_user_id' => User::factory(),
        ];
    }
}
