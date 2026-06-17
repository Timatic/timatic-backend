<?php

namespace Database\Seeders\Dummy;

use App\Models\Budget;
use App\Models\Customer;
use App\Models\Entry;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\MinutesSpentCalculator;
use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EntrySeeder extends Seeder
{
    private Generator $faker;

    private MinutesSpentCalculator $minutesSpentCalculator;

    public function run(): void
    {
        $this->faker = Factory::create('nl_NL');
        $this->minutesSpentCalculator = app(MinutesSpentCalculator::class);

        $budgets = Budget::with(['allowedUsers', 'budgetVersions', 'customer'])
            ->whereIn('budget_type_id', ['project', 'support', 'retainer'])
            ->whereNull('archived_at')
            ->get();

        foreach ($budgets as $budget) {
            $this->createEntriesForBudget($budget);
        }

        $this->createNonBudgetEntries();
    }

    private function createNonBudgetEntries(): void
    {
        /** @var Collection<int, Customer> $customers */
        $customers = Customer::all();
        /** @var Collection<int, User> $users */
        $users = User::all();

        if ($customers->isEmpty() || $users->isEmpty()) {
            return;
        }

        for ($weeksAgo = 51; $weeksAgo >= 0; $weeksAgo--) {
            $weekStart = Carbon::now()->startOfWeek()->subWeeks($weeksAgo);
            $weekEnd = $weekStart->copy()->endOfWeek()->min(Carbon::now());

            $this->createWeeklyEntries($weekStart, $weekEnd, $customers, $users, isInternal: true);
            $this->createWeeklyEntries($weekStart, $weekEnd, $customers, $users, isInternal: false);
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, User>  $users
     */
    private function createWeeklyEntries(Carbon $weekStart, Carbon $weekEnd, Collection $customers, Collection $users, bool $isInternal): void
    {
        $count = $this->faker->numberBetween(10, 20);
        $dates = $this->distributeEntriesAcrossPeriod($weekStart, $weekEnd, $count);

        foreach ($dates as $startedAt) {
            $minutes = $this->faker->numberBetween(15, 60 * 4);
            $minutes = (int) (round($minutes / 5) * 5);

            $customer = $customers->random();
            $user = $users->random();

            $entry = Entry::create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_full_name' => $user->full_name,
                'budget_id' => null,
                'entry_type' => 'regular',
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addMinutes($minutes),
                'description' => $this->getRandomDescription(),
                'ticket_id' => Str::uuid(),
                'ticket_number' => 'TICKET-'.$this->faker->numberBetween(10000, 99999),
                'ticket_type' => 'issue',
                'ticket_title' => $this->faker->sentence(),
                'is_internal' => $isInternal,
                'is_locked' => false,
                'hourly_rate' => null,
            ]);

            $entry->minutes_spent = $this->minutesSpentCalculator->calculate($entry);
            $entry->saveQuietly();
        }
    }

    private function createEntriesForBudget(Budget $budget): void
    {
        $version = $budget->activeVersion();

        if ($version->initial_minutes == 0) {
            return;
        }

        foreach ($budget->periods() as $period) {
            $durations = $this->generateRandomDurations($budget, $period);

            if ($period->getStartDate()->isAfter(Carbon::now())) {
                break;
            }

            $dates = $this->distributeEntriesAcrossPeriod(
                $period->getStartDate(),
                $period->getEndDate()->isAfter(Carbon::now()) ? Carbon::now() : $period->getEndDate(),
                count($durations)
            );

            $users = collect($this->getRandomUsers($budget, count($durations)));

            if ($users->isEmpty()) {
                return;
            }

            foreach ($durations as $index => $minutes) {
                $user = $users->random();

                if (! array_key_exists($index, $dates)) {
                    continue;
                }

                $startedAt = $dates[$index];
                $endedAt = $startedAt->copy()->addMinutes($minutes);

                $entry = Entry::create([
                    'customer_id' => $budget->customer_id,
                    'customer_name' => $budget->customer !== null ? $budget->customer->name : '',
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'user_full_name' => $user->full_name,
                    'budget_id' => $budget->id,
                    'entry_type' => 'regular',
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'description' => $this->getRandomDescription(),
                    'ticket_id' => Str::uuid(),
                    'ticket_number' => $this->generateTicketNumber($budget->budget_type_id, $startedAt),
                    'ticket_type' => 'issue',
                    'ticket_title' => $this->faker->sentence(),
                    'is_internal' => false,
                    'is_locked' => false,
                    'hourly_rate' => null,
                ]);

                $entry->minutes_spent = $this->minutesSpentCalculator->calculate($entry);
                $entry->saveQuietly();
            }
        }
    }

    /** @return array<int, int> */
    private function generateRandomDurations(Budget $budget, Period $period): array
    {
        $targetPercentage = $this->faker->numberBetween(70, 140);
        $targetMinutes = $budget->activeVersion()->initial_minutes;

        if ($period->getEndDate()->isAfter(Carbon::now())) {
            $days = $period->getStartDate()->diffInDays(Carbon::now());
            $fullPeriod = $budget->renewal_frequency == 'monthly' ? 30 : 365;
            $targetMinutes *= ($days / $fullPeriod);
        }

        $targetTotal = (int) ($targetMinutes * $targetPercentage / 100);

        $durations = [];
        $remaining = $targetTotal;

        while ($remaining > 0) {
            $duration = $this->faker->numberBetween(15, 60 * 4);
            $duration = (int) (round($duration / 5) * 5);

            $durations[] = $duration;
            $remaining -= $duration;
        }

        return $durations;
    }

    /** @return array<int, Carbon> */
    private function distributeEntriesAcrossPeriod(Carbon $start, Carbon $end, int $count): array
    {
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $randomDate = $this->faker->dateTimeBetween($start, $end);
            $carbon = Carbon::instance($randomDate);

            while ($carbon->isWeekend()) {
                $carbon->addDay();
            }

            if ($carbon->isAfter($end)) {
                continue;
            }

            $carbon->setTime(
                $this->faker->numberBetween(8, 17),
                $this->faker->randomElement([0, 15, 30, 45])
            );

            $dates[] = $carbon;
        }

        usort($dates, fn ($a, $b) => $a <=> $b);

        return $dates;
    }

    /** @return array<int, User> */
    private function getRandomUsers(Budget $budget, int $count): array
    {
        if ($budget->allowedUsers->isNotEmpty()) {
            $users = [];
            $allowedUsers = $budget->allowedUsers->all();

            for ($i = 0; $i < $count; $i++) {
                $users[] = $this->faker->randomElement($allowedUsers);
            }

            return $users;
        }

        $teams = Team::whereIn('name', ['development', 'support'])->with('users')->get();
        $allUsers = $teams->flatMap(fn ($team) => $team->users)->all();

        if (empty($allUsers)) {
            return [];
        }

        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = $this->faker->randomElement($allUsers);
        }

        return $users;
    }

    private function getRandomDescription(): string
    {
        return $this->faker->randomElement([
            'Nieuwe functionaliteit geïmplementeerd volgens specificaties',
            'Kritieke bug opgelost in productieomgeving',
            'Code review en refactoring werkzaamheden',
            'Database optimalisatie en query tuning',
            'API endpoint ontwikkeling en testing',
            'Frontend component implementatie',
            'Security patch en vulnerability fix',
            'Performance optimalisatie',
            'Documentatie en code cleanup',
            'Integratie met externe diensten',
            'Bugfix en technisch onderhoud',
            'Feature development en uitbreiding',
        ]);
    }

    private function generateTicketNumber(string $budgetTypeId, Carbon $date): string
    {
        return match ($budgetTypeId) {
            'project' => 'JIRA-'.$this->faker->numberBetween(1000, 9999),
            'support', 'retainer' => sprintf(
                'TICKET-%s-%04d',
                $date->format('Ym'),
                $this->faker->numberBetween(1, 9999)
            ),
            default => 'TICKET-'.$this->faker->numberBetween(10000, 99999),
        };
    }
}
