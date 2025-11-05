<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Services\Charts\ChartGeneratorInterface;
use App\Services\Charts\OneRepMaxChartGenerator;
use App\Services\Charts\VolumeProgressionChartGenerator;
use App\Services\Charts\BandProgressionChartGenerator;
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
     * Legacy method - maintained for backward compatibility
     * @deprecated Use generateProgressChart() instead
     */
    public function generateBestPerDay(Collection $liftLogs): array
    {
        $generator = new OneRepMaxChartGenerator();
        return $generator->generate($liftLogs);
    }

    /**
     * Determine the exercise type for chart generation
     * @deprecated Use exercise type strategy getChartType() method instead
     */
    protected function determineExerciseType(Exercise $exercise): string
    {
        if ($exercise->band_type) {
            return 'banded';
        }
        
        if ($exercise->is_bodyweight) {
            return 'bodyweight';
        }
        
        return 'weighted';
    }

    /**
     * Generate appropriate chart data using exercise type strategy
     */
    public function generateProgressChartWithStrategy(Collection $liftLogs, Exercise $exercise): array
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
     * Generate chart data for LiftLogs with all data points.
     * @deprecated Use generateProgressChart() instead
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

    /**
     * Generate chart data for BodyLogs.
     */
    public function generateBodyLogChartData(Collection $bodyLogs, \App\Models\MeasurementType $measurementType): array
    {
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => $measurementType->name,
                    'data' => [],
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'backgroundColor' => 'rgba(255, 99, 132, 1)',
                    'fill' => false,
                ],
            ],
        ];

        if ($bodyLogs->isNotEmpty()) {
            $earliestDate = $bodyLogs->last()->logged_at->startOfDay();
            $latestDate = $bodyLogs->first()->logged_at->endOfDay();

            $currentDate = $earliestDate->copy();
            $dataMap = $bodyLogs->keyBy(function ($item) {
                return $item->logged_at->format('Y-m-d');
            });

            while ($currentDate->lte($latestDate)) {
                $dateString = $currentDate->format('Y-m-d');
                $chartData['labels'][] = $currentDate->format('m/d');
                $chartData['datasets'][0]['data'][] = $dataMap->has($dateString) ? $dataMap[$dateString]->value : null;
                $currentDate->addDay();
            }
        }

        return $chartData;
    }
}