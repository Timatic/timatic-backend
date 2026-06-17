<?php

namespace Database\Seeders\Dummy;

use App\Models\Budget;
use App\Models\BudgetType;
use App\Models\BudgetVersion;
use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    private Generator $faker;

    public function run(): void
    {
        $this->faker = Factory::create('nl_NL');

        $customers = Customer::where('id', '>', 1)->get();
        $budgetTypes = BudgetType::whereNot('id', BudgetType::LEAVE)->get();

        $developmentTeam = Team::where('name', 'development')->first();
        $developers = $developmentTeam !== null ? $developmentTeam->users : new Collection;

        if ($customers->isEmpty()) {
            $this->command->warn('No customers found. Skipping budget creation.');

            return;
        }

        foreach ($customers as $customer) {
            $numberOfBudgets = $this->faker->numberBetween(1, 3);
            $selectedTypes = $budgetTypes->random($numberOfBudgets);

            foreach ($selectedTypes as $budgetType) {
                $this->createBudgetForType($customer, $budgetType, $developers);
            }
        }
    }

    /** @param Collection<int, User> $developers */
    private function createBudgetForType(Customer $customer, BudgetType $budgetType, Collection $developers): void
    {
        $startedAt = Carbon::now()->subMonths($this->faker->numberBetween(1, 6));

        if ($this->faker->boolean(70)) {
            $startedAt = $startedAt->startOfMonth();
        }

        if ($this->faker->boolean()) {
            $startedAt = $startedAt->subMonths(6);
        }

        $endedAt = $startedAt->clone()->addYear();

        $budgetData = [
            'customer_id' => $customer->id,
            'budget_type_id' => $budgetType->id,
            'show_to_customer' => $this->faker->boolean(80),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'archived_at' => null,
            'supervisor_user_id' => null,
        ];

        match ($budgetType->id) {
            'project' => $this->configureProjectBudget($budgetData),
            'support' => $this->configureSupportBudget($budgetData),
            'retainer' => $this->configureRetainerBudget($budgetData),
            default => null,
        };

        $budget = Budget::create($budgetData);

        if ($budgetType->id === 'project' && $this->faker->boolean(75) && $developers->isNotEmpty()) {
            $budget->allowedUsers()->attach([$developers->random()->id, $developers->random()->id]);
            $budget->supervisor_user_id = $developers->random()->id;
            $budget->save();
        }

        $this->createBudgetVersion($budget, $budgetType, $startedAt);
    }

    /** @param array<string, mixed> $budgetData */
    private function configureProjectBudget(array &$budgetData): void
    {
        $budgetData['renewal_frequency'] = null;
    }

    /** @param array<string, mixed> $budgetData */
    private function configureSupportBudget(array &$budgetData): void
    {
        $budgetData['renewal_frequency'] = 'monthly';
    }

    /** @param array<string, mixed> $budgetData */
    private function configureRetainerBudget(array &$budgetData): void
    {
        $budgetData['renewal_frequency'] = null;
    }

    private function createBudgetVersion(Budget $budget, BudgetType $budgetType, Carbon $effectiveFrom): void
    {
        $initialMinutes = $this->faker->numberBetween(20, 200) * 60;

        if ($budget->renewal_frequency == 'monthly') {
            $initialMinutes = $this->faker->randomElement([16, 32, 64, 96]) * 60;
        }

        $hourlyRate = $this->faker->randomFloat(2, 60, 150);
        $totalPrice = ($initialMinutes / 60) * $hourlyRate;

        $versionData = [
            'budget_id' => $budget->id,
            'title' => $this->generateTitle($budgetType->id),
            'description' => $this->faker->boolean(70) ? $this->generateDescription($budgetType->id) : null,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'initial_minutes' => $initialMinutes,
            'total_price' => number_format($totalPrice, 2, '.', ''),
        ];

        match ($budgetType->id) {
            'project' => $versionData['change_id'] = 'CHG-'.$this->faker->numberBetween(10000, 99999),
            'support', 'retainer' => $versionData['contract_id'] = 'CTR-'.$this->faker->year().'-'.$this->faker->numberBetween(100, 999),
            default => null,
        };

        BudgetVersion::create($versionData);
    }

    private function generateTitle(string $budgetTypeId): string
    {
        return match ($budgetTypeId) {
            'project' => $this->faker->randomElement([
                'Website Vernieuwing '.$this->faker->year(),
                'App Ontwikkeling '.$this->faker->word(),
                'Platform Migratie',
                'CRM Implementatie',
                'E-commerce Uitbreiding',
                'API Integratie '.$this->faker->company(),
            ]),
            'support' => $this->faker->randomElement([
                'Support Uren '.$this->faker->monthName(),
                'Maandelijks Onderhoud',
                'Technische Ondersteuning',
                'Helpdesk Support',
                'IT Support Pakket',
            ]),
            'retainer' => $this->faker->randomElement([
                'Strippenkaart Uren '.$this->faker->year(),
                'Vaste Afname Uren',
                'Jaarcontract Ontwikkeling',
                'Advies & Ontwikkeling',
                'Strategisch Partnerschap',
            ]),
            default => $this->faker->sentence(3),
        };
    }

    private function generateDescription(string $budgetTypeId): string
    {
        return match ($budgetTypeId) {
            'project' => $this->faker->randomElement([
                'Volledige herziening van de website met nieuwe functionaliteiten en verbeterde gebruikerservaring.',
                'Ontwikkeling van een mobiele applicatie voor iOS en Android met backend integratie.',
                'Migratie van het bestaande platform naar een moderne cloud-infrastructuur.',
                'Implementatie van een CRM-systeem met koppelingen naar bestaande systemen.',
            ]),
            'support' => $this->faker->randomElement([
                'Maandelijkse support uren voor onderhoud, bugfixes en kleine aanpassingen.',
                'Technische ondersteuning en monitoring van de productieomgeving.',
                'Helpdesk support voor eindgebruikers en technische vraagstukken.',
            ]),
            'retainer' => $this->faker->randomElement([
                'Vaste afname van ontwikkel- en adviesuren voor strategische projecten.',
                'Jaarcontract voor continue ontwikkeling en onderhoud van applicaties.',
                'Strategisch partnerschap met vaste capaciteit voor diverse werkzaamheden.',
            ]),
            default => $this->faker->paragraph(),
        };
    }
}
