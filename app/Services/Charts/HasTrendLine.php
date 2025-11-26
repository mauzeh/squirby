<?php

namespace App\Services\Charts;

trait HasTrendLine
{
    /**
     * Calculate linear regression trend line from data points
     * 
     * @param array $dataPoints Array of ['x' => timestamp/date, 'y' => value]
     * @return array Trend line data points (start and end)
     */
    protected function calculateTrendLine(array $dataPoints): array
    {
        if (count($dataPoints) < 2) {
            return [];
        }

        // Convert ISO dates to timestamps for calculation
        $points = array_map(function ($point) {
            $timestamp = is_numeric($point['x']) 
                ? $point['x'] 
                : strtotime($point['x']);
            
            return [
                'x' => $timestamp,
                'y' => $point['y']
            ];
        }, $dataPoints);

        // Filter out null values
        $points = array_filter($points, function($point) {
            return $point['y'] !== null;
        });

        if (count($points) < 2) {
            return [];
        }

        $points = array_values($points);

        $n = count($points);
        $sumX = array_sum(array_column($points, 'x'));
        $sumY = array_sum(array_column($points, 'y'));
        $sumXY = array_sum(array_map(function ($p) {
            return $p['x'] * $p['y'];
        }, $points));
        $sumX2 = array_sum(array_map(function ($p) {
            return $p['x'] * $p['x'];
        }, $points));

        // Avoid division by zero
        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return [];
        }

        // Calculate slope (m) and intercept (b) for y = mx + b
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
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

    /**
     * Create a trend line dataset configuration
     * 
     * @param array $trendLineData Trend line data points
     * @return array Chart.js dataset configuration
     */
    protected function createTrendLineDataset(array $trendLineData): array
    {
        return [
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
}
