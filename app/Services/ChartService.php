<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Services\Charts\ChartGeneratorInterface;
use App\Services\Charts\OneRepMaxChartGenerator;
use App\Services\Charts\VolumeProgressionChartGenerator;
use App\Services\Charts\BandProgressionChartGenerator;
use App\Services\Charts\CardioProgressionChartGenerator;
use App\Services\Charts\HoldDurationProgressionChartGenerator;
use App\Models\Exercise;

/**
 * Class ChartService
 *
 * This service delegates chart generation to specialized generators based on exercise type.
 * It maintains backward compatibility while providing a clean separation of concerns.
 */
class ChartService
{
    protected array $generators;

    public function __construct()
    {
        $this->generators = [
            new OneRepMaxChartGenerator(),
            new VolumeProgressionChartGenerator(),
            new BandProgressionChartGenerator(),
            new CardioProgressionChartGenerator(),
            new HoldDurationProgressionChartGenerator(),
        ];
    }

    /**
     * Generate appropriate chart data based on exercise type
     */
    public function generateProgressChart(Collection $liftLogs, Exercise $exercise): array
    {
        // Use exercise type strategy to determine chart type
        $strategy = $exercise->getTypeStrategy();
        $chartType = $strategy->getChartType();
        
        foreach ($this->generators as $generator) {
            if ($generator->supports($chartType)) {
                return $generator->generate($liftLogs);
            }
        }

        // Fallback to empty chart data
        return ['datasets' => []];
    }



    /**
     * Generate chart data for BodyLogs.
     */
    public function generateBodyLogChartData(Collection $bodyLogs, \App\Models\MeasurementType $measurementType): array
    {
        $dataPoints = [];
        if ($bodyLogs->isNotEmpty()) {
            $earliestDate = $bodyLogs->last()->logged_at->startOfDay();
            $latestDate = $bodyLogs->first()->logged_at->endOfDay();

            $currentDate = $earliestDate->copy();
            $dataMap = $bodyLogs->keyBy(function ($item) {
                return $item->logged_at->format('Y-m-d');
            });

            while ($currentDate->lte($latestDate)) {
                $dateString = $currentDate->format('Y-m-d');
                $dataPoints[] = [
                    'x' => $dateString,
                    'y' => $dataMap->has($dateString) ? $dataMap[$dateString]->value : null,
                ];
                $currentDate->addDay();
            }
        }

        $datasets = [
            [
                'label' => $measurementType->name,
                'data' => $dataPoints,
                'backgroundColor' => 'rgba(0, 123, 255, 0.5)',
                'borderColor' => 'rgba(0, 123, 255, 1)',
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'spanGaps' => true,
            ]
        ];

        // Add trend line
        $trendLineData = $this->calculateTrendLine($dataPoints);
        if (!empty($trendLineData)) {
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
        // We need at least 2 points to calculate a trend.
        $points = array_filter($dataPoints, function($point) {
            return $point['y'] !== null;
        });

        if (count($points) < 2) {
            return [];
        }
        
        $points = array_values($points);

        // Convert Y-m-d dates to timestamps for calculation
        $points = array_map(function ($point) {
            return [
                'x' => strtotime($point['x']),
                'y' => $point['y']
            ];
        }, $points);

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
        $slope = $denominator ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Generate trend line points at the start and end
        $firstTimestamp = $points[0]['x'];
        $lastTimestamp = $points[count($points) - 1]['x'];

        return [
            [
                'x' => date('Y-m-d', $firstTimestamp),
                'y' => round($slope * $firstTimestamp + $intercept, 1)
            ],
            [
                'x' => date('Y-m-d', $lastTimestamp),
                'y' => round($slope * $lastTimestamp + $intercept, 1)
            ]
        ];
    }
}