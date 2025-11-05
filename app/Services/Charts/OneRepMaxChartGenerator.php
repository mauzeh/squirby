<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class OneRepMaxChartGenerator implements ChartGeneratorInterface
{
    public function generate(Collection $liftLogs): array
    {
        $bestLiftLogsPerDay = $liftLogs->groupBy(function ($liftLog) {
            return $liftLog->logged_at->format('Y-m-d');
        })->map(function ($logsOnDay) {
            return $logsOnDay->sortByDesc('best_one_rep_max')->first();
        });

        return [
            'datasets' => [
                [
                    'label' => '1RM (est.)',
                    'data' => $bestLiftLogsPerDay->map(function ($liftLog) {
                        return [
                            'x' => $liftLog->logged_at->toIso8601String(),
                            'y' => $liftLog->best_one_rep_max,
                        ];
                    })->values()->toArray(),
                    'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                    'borderColor' => 'rgba(0, 123, 255, 1)',
                    'borderWidth' => 2
                ]
            ]
        ];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['weighted', 'one_rep_max']);
    }
}