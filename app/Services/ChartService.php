<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Services\Charts\ChartGeneratorInterface;
use App\Services\Charts\OneRepMaxChartGenerator;
use App\Services\Charts\VolumeProgressionChartGenerator;
use App\Services\Charts\BandProgressionChartGenerator;
use App\Services\Charts\CardioProgressionChartGenerator;
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