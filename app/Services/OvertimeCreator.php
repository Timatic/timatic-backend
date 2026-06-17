<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Overtime;
use App\Models\OvertimeRule;
use App\Models\OvertimeType;
use Carbon\Carbon;
use Illuminate\Contracts\Config\Repository as Config;
use Yasumi\Provider\AbstractProvider;
use Yasumi\Yasumi;

class OvertimeCreator
{
    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(
        Entry $entry,
        bool $hasOvertime,
        bool $hasCustomerOvertime,
        Carbon|string|null $overtimeStartedAtOverride = null,
        Carbon|string|null $overtimeEndedAtOverride = null
    ): void {
        $workingHours = $this->config->get('timatic.working_hours');
        $timezone = config('timatic.preferred_timezone');

        $startWorkingHoursStartedAt = $entry->started_at
            ->copy()
            ->timezone($timezone)
            ->setTimeFromTimeString($workingHours['start'])
            ->timezone('UTC');

        $endWorkingHoursStartedAt = $entry->started_at
            ->copy()
            ->timezone($timezone)
            ->setTimeFromTimeString($workingHours['end'])
            ->timezone('UTC');

        $startWorkingHoursEndedAt = $entry->ended_at
            ->copy()
            ->timezone($timezone)
            ->setTimeFromTimeString($workingHours['start'])
            ->timezone('UTC');

        $endWorkingHoursEndedAt = $entry->ended_at
            ->copy()
            ->timezone($timezone)
            ->setTimeFromTimeString($workingHours['end'])
            ->timezone('UTC');

        $overtimeStartedAt = $entry->started_at;
        $overtimeEndedAt = $entry->ended_at;

        if (
            $this->matchesDay($entry->started_at->copy()->timezone($timezone), $workingHours['days'])
            && $entry->started_at->betweenIncluded($startWorkingHoursStartedAt, $endWorkingHoursStartedAt)
        ) {
            $overtimeStartedAt = $endWorkingHoursStartedAt;
        }

        if (
            $this->matchesDay($entry->ended_at->copy()->timezone($timezone), $workingHours['days'])
            && $entry->ended_at->betweenIncluded($startWorkingHoursEndedAt, $endWorkingHoursEndedAt)
        ) {
            $overtimeEndedAt = $startWorkingHoursEndedAt;
        }

        $overtime = $entry->personalOvertime ?? new Overtime;

        if (! $hasOvertime && $overtime->exists) {
            $overtime->delete();
        }

        if ($hasOvertime) {
            if (! is_null($overtimeStartedAtOverride)) {
                $overtimeStartedAtOverride = Carbon::parse($overtimeStartedAtOverride);
            }

            if (! is_null($overtimeEndedAtOverride)) {
                $overtimeEndedAtOverride = Carbon::parse($overtimeEndedAtOverride);
            }

            $overtime->entry_id = $entry->id;
            $overtime->overtime_type_id = OvertimeType::PERSONAL;
            $overtime->started_at = $overtimeStartedAtOverride ?? $overtimeStartedAt;
            $overtime->ended_at = $overtimeEndedAtOverride ?? $overtimeEndedAt;
            $overtime->percentages = (object) $this->calculatePercentageDistribution($overtime);
            $overtime->save();
        }

        $customerOvertime = $entry->customerOvertime ?? new Overtime;

        if (! $hasCustomerOvertime && $customerOvertime->exists) {
            $customerOvertime->delete();
        }

        if ($hasCustomerOvertime) {
            $customerOvertime->entry_id = $entry->id;
            $customerOvertime->overtime_type_id = OvertimeType::CUSTOMER;
            $customerOvertime->started_at = $overtimeStartedAt;
            $customerOvertime->ended_at = $overtimeEndedAt;
            $customerOvertime->percentages = (object) $this->calculatePercentageDistribution($customerOvertime);
            $customerOvertime->save();
        }
    }

    /**
     * @return array<int|string, array<string, mixed>>|null
     */
    public function calculatePercentageDistribution(Overtime $overtime): ?array
    {
        $startToDetermine = $overtime->started_at->copy()->timezone(config('timatic.preferred_timezone'));
        $endToDetermine = $overtime->ended_at->copy()->timezone(config('timatic.preferred_timezone'));

        $rules = OvertimeRule::byPriority()->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $percentages = [];

        $currentMinute = $startToDetermine->copy();

        do {
            foreach ($rules as $rule) {
                $start = $startToDetermine->copy()->setTimeFromTimeString($rule->start_time);
                $end = $startToDetermine->copy()->setTimeFromTimeString($rule->end_time);

                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }

                $nextDayStart = $start->copy()->addDay();
                $nextDayEnd = $end->copy()->addDay();

                if ($this->matchesDay($currentMinute, $rule->days)
                    && (
                        $currentMinute->isBetween($start, $end)
                        || $currentMinute->isBetween($nextDayStart, $nextDayEnd)
                    )
                ) {
                    $percentages[$rule->key] = [
                        'minutes' => ($percentages[$rule->key]['minutes'] ?? 0) + 1,
                        'percentage' => $rule->percentage,
                    ];

                    break;
                }
            }
            $currentMinute->addMinute();
        } while ($currentMinute->lessThan($endToDetermine));

        return $percentages;
    }

    protected function isHoliday(Carbon $dateTime): bool
    {
        /** @var AbstractProvider $holidays */
        $holidays = Yasumi::create('Netherlands', $dateTime->year);
        $holidays = $holidays->on($dateTime);

        foreach ($holidays as $day) {
            if ($day->getType() == 'official' && $day->shortName != 'liberationDay') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int|string>  $days
     */
    protected function matchesDay(Carbon $dateTime, array $days): bool
    {
        return (
            in_array('holiday', $days)
            && $this->isHoliday($dateTime)
        )
        ||
        (
            in_array($dateTime->dayOfWeekIso, $days)
        );
    }
}
