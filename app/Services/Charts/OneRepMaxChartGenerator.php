<?php

namespace App\Services\Charts;

use Illuminate\Support\Collection;

class OneRepMaxChartGenerator implements ChartGeneratorInterface
{
    use HasTrendLine;
    public function generate(Collection $liftLogs): array
    {
        $bestLiftLogsPerDay = $liftLogs->groupBy(function ($liftLog) {
            return $liftLog->logged_at->format('Y-m-d');
        })->map(function ($logsOnDay) {
            return $logsOnDay->sortByDesc('best_one_rep_max')->first();
        });

        $dataPoints = $bestLiftLogsPerDay
            ->filter(function ($liftLog) {
                // Exclude logs with 0 or null 1RM (e.g., high-rep sets only)
                return $liftLog->best_one_rep_max > 0;
            })
            ->map(function ($liftLog) {
                return [
                    'x' => $liftLog->logged_at->toIso8601String(),
                    'y' => $liftLog->best_one_rep_max,
                ];
            })->values()->toArray();

        // If no valid data points (e.g., all high-rep sets), return empty datasets
        if (empty($dataPoints)) {
            return ['datasets' => []];
        }

        $datasets = [
            [
                'label' => '1RM (est.)',
                'data' => $dataPoints,
                'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                'borderColor' => 'rgba(0, 123, 255, 1)',
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6
            ]
        ];

        // Add trend line if we have at least 2 data points
        if (count($dataPoints) >= 2) {
            $trendLineData = $this->calculateTrendLine($dataPoints);
            if (!empty($trendLineData)) {
                $datasets[] = $this->createTrendLineDataset($trendLineData);
            }
        }

        return ['datasets' => $datasets];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['weighted', 'one_rep_max', 'weight_progression']);
    }
}