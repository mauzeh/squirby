<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Class ChartService
 *
 * This service is responsible for generating chart data for lift logs.
 * It provides methods to generate chart data with all data points, or with only the best lift log per day.
 * This helps to keep the controllers clean and the logic for chart data generation in a single place.
 *
 * Example Usage:
 *
 * $chartService = new ChartService();
 * $liftLogs = new Collection([...]); // Collection of LiftLog models
 * $chartData = $chartService->generateBestPerDay($liftLogs);
 */
class ChartService
{
    /**
     * Generate chart data with only the best lift log per day.
     *
     * This method groups the lift logs by day and then finds the lift log with the highest 'best_one_rep_max' for each day.
     * This is useful for showing a trend line of the best performance over time.
     *
     * @param Collection $liftLogs A collection of LiftLog models.
     * @return array The chart data in a format that can be used by a charting library.
     *
     * Example Input ($liftLogs):
     *
     * new Collection([
     *     (object)['logged_at' => Carbon::parse('2025-01-01'), 'best_one_rep_max' => 100],
     *     (object)['logged_at' => Carbon::parse('2025-01-01'), 'best_one_rep_max' => 110],
     *     (object)['logged_at' => Carbon::parse('2025-01-02'), 'best_one_rep_max' => 120],
     * ]);
     *
     * Example Calculation:
     *
     * 1. Group by day:
     *    '2025-01-01' => [ (object)['best_one_rep_max' => 100], (object)['best_one_rep_max' => 110] ],
     *    '2025-01-02' => [ (object)['best_one_rep_max' => 120] ]
     *
     * 2. Find best log per day:
     *    '2025-01-01' => (object)['best_one_rep_max' => 110],
     *    '2025-01-02' => (object)['best_one_rep_max' => 120]
     *
     * Example Output:
     *
     * [
     *     'datasets' => [
     *         [
     *             'label' => '1RM (est.)',
     *             'data' => [
     *                 ['x' => '2025-01-01T00:00:00...', 'y' => 110],
     *                 ['x' => '2025-01-02T00:00:00...', 'y' => 120],
     *             ],
     *             ...
     *         ]
     *     ]
     * ]
     */
    public function generateBestPerDay(Collection $liftLogs): array
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
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    /**
     * Generate chart data with all data points.
     *
     * This method includes all lift logs in the chart data, without any grouping or filtering.
     * This is useful for showing all the data points for a given period.
     *
     * @param Collection $liftLogs A collection of LiftLog models.
     * @return array The chart data in a format that can be used by a charting library.
     *
     * Example Input ($liftLogs):
     *
     * new Collection([
     *     (object)['logged_at' => Carbon::parse('2025-01-01'), 'best_one_rep_max' => 100],
     *     (object)['logged_at' => Carbon::parse('2025-01-01'), 'best_one_rep_max' => 110],
     *     (object)['logged_at' => Carbon::parse('2025-01-02'), 'best_one_rep_max' => 120],
     * ]);
     *
     * Example Output:
     *
     * [
     *     'datasets' => [
     *         [
     *             'label' => '1RM (est.)',
     *             'data' => [
     *                 ['x' => '2025-01-01T00:00:00...', 'y' => 100],
     *                 ['x' => '2025-01-01T00:00:00...', 'y' => 110],
     *                 ['x' => '2025-01-02T00:00:00...', 'y' => 120],
     *             ],
     *             ...
     *         ]
     *     ]
     * ]
     */
    public function generateAllDataPoints(Collection $liftLogs): array
    {
        return [
            'datasets' => [
                [
                    'label' => '1RM (est.)',
                    'data' => $liftLogs->map(function ($liftLog) {
                        return [
                            'x' => $liftLog->logged_at->toIso8601String(),
                            'y' => $liftLog->best_one_rep_max,
                        ];
                    })->values()->toArray(),
                    'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                    'borderColor' => 'rgba(0, 123, 255, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];
    }
}
