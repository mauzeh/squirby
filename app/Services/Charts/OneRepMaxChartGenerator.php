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
            $datasets[] = [
                'label' => 'Trend',
                'data' => $trendLineData,
                'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                'borderColor' => 'rgba(255, 99, 132, 0.8)',
                'borderWidth' => 2,
                'borderDash' => [5, 5],
                'pointRadius' => 0,
                'pointHoverRadius' => 0,
                'fill' => false
            ];
        }

        return ['datasets' => $datasets];
    }

    /**
     * Calculate linear regression trend line
     */
    private function calculateTrendLine(array $dataPoints): array
    {
        if (count($dataPoints) < 2) {
            return [];
        }

        // Convert ISO dates to timestamps for calculation
        $points = array_map(function ($point) {
            return [
                'x' => strtotime($point['x']),
                'y' => $point['y']
            ];
        }, $dataPoints);

        $n = count($points);
        $sumX = array_sum(array_column($points, 'x'));
        $sumY = array_sum(array_column($points, 'y'));
        $sumXY = array_sum(array_map(function ($p) {
            return $p['x'] * $p['y'];
        }, $points));
        $sumX2 = array_sum(array_map(function ($p) {
            return $p['x'] * $p['x'];
        }, $points));

        // Calculate slope (m) and intercept (b) for y = mx + b
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Generate trend line points at the start and end
        $firstTimestamp = $points[0]['x'];
        $lastTimestamp = $points[count($points) - 1]['x'];

        return [
            [
                'x' => date('c', $firstTimestamp),
                'y' => round($slope * $firstTimestamp + $intercept, 1)
            ],
            [
                'x' => date('c', $lastTimestamp),
                'y' => round($slope * $lastTimestamp + $intercept, 1)
            ]
        ];
    }

    public function supports(string $exerciseType): bool
    {
        return in_array($exerciseType, ['weighted', 'one_rep_max', 'weight_progression']);
    }
}