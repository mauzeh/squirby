<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Services\Charts\ChartGeneratorInterface;
use App\Services\Charts\OneRepMaxChartGenerator;
use App\Services\Charts\VolumeProgressionChartGenerator;
use App\Services\Charts\BandProgressionChartGenerator;
use App\Services\Charts\CardioProgressionChartGenerator;
use App\Services\Charts\HoldDurationProgressionChartGenerator;
use App\Services\Charts\HasTrendLine;
use App\Models\Exercise;

/**
 * Class ChartService
 *
 * This service delegates chart generation to specialized generators based on exercise type.
 * It maintains backward compatibility while providing a clean separation of concerns.
 */
class ChartService
{
    use HasTrendLine;
    
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
            // Convert ISO 8601 dates back to Y-m-d format for body logs
            $trendLineData = array_map(function($point) {
                return [
                    'x' => date('Y-m-d', strtotime($point['x'])),
                    'y' => $point['y']
                ];
            }, $trendLineData);
            
            $datasets[] = $this->createTrendLineDataset($trendLineData);
        }

        return ['datasets' => $datasets];
    }
}
