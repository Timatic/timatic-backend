<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Budget;
use App\Models\EntrySuggestion;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedEntrySuggestionsCommand extends Command
{
    protected $signature = 'timatic:dummy-suggestions {--user= : Email address of the user to create suggestions for}';

    protected $description = 'Seed dummy entry suggestions for a specific user';

    private Generator $faker;

    /** @var Budget[] */
    private array $budgets = [];

    /** @var string[] */
    private array $sources = [];

    /** @var int[] */
    private array $fixedHours = [6, 9, 11, 14, 16];

    public function handle(): int
    {
        $originalQueueConnection = config('queue.default');
        config(['queue.default' => 'sync']);

        $this->faker = Factory::create('nl_NL');

        $email = $this->option('user') ?? $this->ask('User email address');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email: $email");

            return self::FAILURE;
        }

        $this->budgets = Budget::with(['customer', 'allowedUsers'])
            ->whereIn('budget_type_id', ['project', 'support', 'retainer'])
            ->whereNull('archived_at')
            ->where(function ($query) use ($user) {
                $query->whereDoesntHave('allowedUsers')
                    ->orWhereHas('allowedUsers', fn ($q) => $q->where('users.id', $user->id));
            })
            ->get()
            ->all();

        if (empty($this->budgets)) {
            $this->warn('No budgets found. Skipping entry suggestion creation.');

            return self::SUCCESS;
        }

        $this->sources = ['jira', 'google_calendar', 'bitbucket'];

        $workdays = $this->getLastFiveWorkdays();

        $this->deleteExistingEvents($user, $workdays);

        foreach ($workdays as $dayIndex => $workday) {
            shuffle($this->sources);
            foreach ($this->sources as $sourceIndex => $sourceId) {
                $this->createEntrySuggestion($user, $workday, $sourceId, $dayIndex, $sourceIndex);
            }
        }

        config(['queue.default' => $originalQueueConnection]);

        return self::SUCCESS;
    }

    /** @param array<int, Carbon> $workdays */
    private function deleteExistingEvents(User $user, array $workdays): void
    {
        $events = Event::where('user_id', $user->id)
            ->where(function ($query) use ($workdays) {
                foreach ($workdays as $workday) {
                    $query->orWhereBetween('started_at', [
                        $workday->copy()->startOfDay(),
                        $workday->copy()->endOfDay(),
                    ]);
                }
            })
            ->get();

        $suggestionIds = Activity::whereIn('id', $events->pluck('activity_id')->filter())
            ->whereNotNull('entry_suggestion_id')
            ->pluck('entry_suggestion_id');

        EntrySuggestion::whereIn('id', $suggestionIds)->delete();

        Event::whereIn('id', $events->pluck('id'))->delete();
    }

    /** @return array<int, Carbon> */
    private function getLastFiveWorkdays(): array
    {
        $workdays = [];
        $date = Carbon::today();

        while (count($workdays) < 5) {
            if (! $date->isWeekend()) {
                $workdays[] = $date->copy();
            }
            $date->subDay();
        }

        return array_reverse($workdays);
    }

    private function createEntrySuggestion(User $user, Carbon $date, string $sourceId, int $dayIndex, int $sourceIndex): void
    {
        $budget = $this->faker->randomElement($this->budgets);
        $hour = $this->fixedHours[($dayIndex * count($this->sources) + $sourceIndex) % count($this->fixedHours)];
        $startedAt = $date->copy()->setTime($hour, $this->faker->randomElement([0, 15, 30, 45]));
        $endedAt = $startedAt->copy()->addMinutes($this->faker->numberBetween(1, 8) * 15);

        $fields = match ($sourceId) {
            'google_calendar' => $this->googleCalendarFields(),
            'bitbucket' => $this->bitbucketFields(),
            default => $this->jiraFields(),
        };

        Event::create([
            'source_id' => $sourceId,
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'customer_id' => $budget->customer_id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'is_internal' => false,
            ...$fields,
        ]);
    }

    /** @return array<string, mixed> */
    private function jiraFields(): array
    {
        return [
            'ticket_id' => Str::uuid(),
            'ticket_number' => 'JIRA-'.$this->faker->numberBetween(1000, 9999),
            'ticket_type' => 'issue',
            'title' => $this->faker->randomElement([
                'Backend API ontwikkeling',
                'Database migratie uitvoeren',
                'Performance optimalisatie',
                'Security update implementeren',
                'Feature development sprint',
            ]),
            'description' => $this->faker->randomElement([
                'Nieuwe functionaliteit geïmplementeerd volgens specificaties',
                'Kritieke bug opgelost in productieomgeving',
                'Code review en refactoring werkzaamheden',
                'Database optimalisatie en query tuning',
                'API endpoint ontwikkeling en testing',
            ]),
            'event_type_id' => 'issue_worklog_created',
        ];
    }

    /** @return array<string, mixed> */
    private function googleCalendarFields(): array
    {
        return [
            'ticket_id' => null,
            'ticket_number' => null,
            'ticket_type' => null,
            'title' => $this->faker->randomElement([
                'Klant overleg',
                'Sprint planning meeting',
                'Code review sessie',
                'Stand-up meeting',
                'Architectuur bespreking',
            ]),
            'description' => $this->faker->randomElement([
                'Wekelijks statusoverleg met de klant',
                'Sprint planning voor de komende twee weken',
                'Code review van recente pull requests',
                'Dagelijkse stand-up met het team',
                'Technische bespreking over systeemarchitectuur',
            ]),
            'event_type_id' => 'calendar_event_started',
        ];
    }

    /** @return array<string, mixed> */
    private function bitbucketFields(): array
    {
        $eventTypeId = $this->faker->randomElement(['commit_pushed', 'pr_merged', 'pr_approved']);

        $title = $eventTypeId === 'commit_pushed'
            ? $this->faker->randomElement([
                'Fix: resolve null pointer in budget calculation',
                'Refactor: extract payment service logic',
                'Add: implement time entry validation',
                'Update: improve API response structure',
                'Fix: correct overtime calculation edge case',
            ])
            : $this->faker->randomElement([
                'Feature/time-entry-bulk-import',
                'Fix/budget-period-calculation',
                'Refactor/api-authentication-flow',
                'Feature/customer-export-csv',
                'Fix/overtime-rounding-error',
            ]);

        return [
            'ticket_id' => null,
            'ticket_number' => null,
            'ticket_type' => null,
            'title' => $title,
            'description' => null,
            'event_type_id' => $eventTypeId,
        ];
    }
}
