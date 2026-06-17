<?php

namespace App\Filament\Resources\OvertimeRules\Pages;

use App\Filament\Resources\OvertimeRules\OvertimeRuleResource;
use App\Models\OvertimeRule;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class ListOvertimeRules extends ListRecords
{
    protected static string $resource = OvertimeRuleResource::class;

    private const int MINUTES_IN_DAY = 1440;

    /** @var array<int|string> */
    private const array DAY_KEYS = [1, 2, 3, 4, 5, 6, 7, 'holiday'];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getHeader(): ?View
    {
        $warnings = $this->conflictWarnings();

        if (empty($warnings)) {
            return null;
        }

        return view('filament.overtime-rule-warnings', ['warnings' => $warnings]);
    }

    /** @return array<string> */
    private function conflictWarnings(): array
    {
        $rules = OvertimeRule::all();
        $warnings = [];

        foreach (self::DAY_KEYS as $day) {
            $dayRules = $rules->filter(fn (OvertimeRule $rule) => in_array($day, $rule->days));

            if ($dayRules->isEmpty()) {
                continue;
            }

            $intervals = $this->toIntervals($dayRules);

            foreach ($this->detectOverlaps($intervals, $day) as $warning) {
                $warnings[] = $warning;
            }

            foreach ($this->detectGaps($intervals, $day) as $warning) {
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }

    /**
     * @param  Collection<int, OvertimeRule>  $rules
     * @return array<int, array{start: int, end: int, key: string}>
     */
    private function toIntervals(Collection $rules): array
    {
        $intervals = $rules->map(function (OvertimeRule $rule): array {
            $start = $this->timeToMinutes($rule->start_time);
            $end = $this->timeToMinutes($rule->end_time);

            if ($end <= $start) {
                $end += self::MINUTES_IN_DAY;
            }

            return ['start' => $start, 'end' => $end, 'key' => $rule->key];
        })->values()->all();

        usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $intervals;
    }

    /**
     * @param  array<int, array{start: int, end: int, key: string}>  $intervals
     * @return array<string>
     */
    private function detectOverlaps(array $intervals, int|string $day): array
    {
        $label = $this->dayLabels()[$day];
        $warnings = [];

        for ($i = 0; $i < count($intervals) - 1; $i++) {
            $current = $intervals[$i];
            $next = $intervals[$i + 1];

            if ($current['end'] > $next['start']) {
                $overlapStart = $this->minutesToTime($next['start']);
                $overlapEnd = $this->minutesToTime(min($current['end'], $next['end']));
                $warnings[] = 'Overlap on '.$label.': "'.$current['key'].'" and "'.$next['key'].'" overlap between '.$overlapStart.'–'.$overlapEnd.'.';
            }
        }

        return $warnings;
    }

    /**
     * @param  array<int, array{start: int, end: int, key: string}>  $intervals
     * @return array<string>
     */
    private function detectGaps(array $intervals, int|string $day): array
    {
        $label = $this->dayLabels()[$day];
        $warnings = [];
        $merged = $this->mergeIntervals($intervals);

        if ($merged[0]['start'] > 0) {
            $warnings[] = 'Gap on '.$label.': 00:00–'.$this->minutesToTime($merged[0]['start']).' is not covered by any rule.';
        }

        for ($i = 0; $i < count($merged) - 1; $i++) {
            if ($merged[$i]['end'] < $merged[$i + 1]['start']) {
                $start = $this->minutesToTime($merged[$i]['end']);
                $end = $this->minutesToTime($merged[$i + 1]['start']);
                $warnings[] = 'Gap on '.$label.': '.$start.'–'.$end.' is not covered by any rule.';
            }
        }

        $last = end($merged);

        if ($last['end'] < self::MINUTES_IN_DAY) {
            $warnings[] = 'Gap on '.$label.': '.$this->minutesToTime($last['end']).'–00:00 is not covered by any rule.';
        }

        return $warnings;
    }

    /**
     * @param  array<int, array{start: int, end: int, key: string}>  $intervals
     * @return array<int, array{start: int, end: int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }

        $merged = [['start' => $intervals[0]['start'], 'end' => $intervals[0]['end']]];

        for ($i = 1; $i < count($intervals); $i++) {
            $last = &$merged[count($merged) - 1];

            if ($intervals[$i]['start'] <= $last['end']) {
                $last['end'] = max($last['end'], $intervals[$i]['end']);
            } else {
                $merged[] = ['start' => $intervals[$i]['start'], 'end' => $intervals[$i]['end']];
            }
        }

        return $merged;
    }

    /** @return array<int|string, string> */
    private function dayLabels(): array
    {
        return [
            1 => __('messages.days.monday'),
            2 => __('messages.days.tuesday'),
            3 => __('messages.days.wednesday'),
            4 => __('messages.days.thursday'),
            5 => __('messages.days.friday'),
            6 => __('messages.days.saturday'),
            7 => __('messages.days.sunday'),
            'holiday' => __('messages.days.holiday'),
        ];
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return ((int) $hours * 60) + (int) $minutes;
    }

    private function minutesToTime(int $minutes): string
    {
        $minutes = $minutes % self::MINUTES_IN_DAY;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
