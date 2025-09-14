<?php

namespace App\Services;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\BodyLog;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use App\Services\IngredientTsvProcessorService;

class TsvImporterService
{
    protected $ingredientTsvProcessorService;

    public function __construct(IngredientTsvProcessorService $ingredientTsvProcessorService)
    {
        $this->ingredientTsvProcessorService = $ingredientTsvProcessorService;
    }

    public function importFoodLogs(string $tsvData, string $date, int $userId): array
    {
        $date = Carbon::parse($date);
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 5) {
                $invalidRows[] = $row;
                continue;
            }

            $ingredient = Ingredient::where('name', $columns[2])->first();

            if ($ingredient) {
                $loggedAt = $this->parseDate($date->format('Y-m-d') . ' ' . $columns[1]);

                if (!$loggedAt) {
                    $invalidRows[] = $row;
                    continue;
                }

                FoodLog::create([
                    'user_id' => $userId,
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->base_unit_id,
                    'quantity' => $columns[4],
                    'logged_at' => $loggedAt,
                    'notes' => $columns[3],
                ]);
                $importedCount++;
            } else {
                $notFound[] = $columns[2];
            }
        }

        return [
            'importedCount' => $importedCount,
            'notFound' => $notFound,
            'invalidRows' => $invalidRows,
        ];
    }

    public function importLiftLogs(string $tsvData, string $date, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $notFound = [];
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 6) {
                $invalidRows[] = $row;
                continue;
            }

            $exercise = \App\Models\Exercise::where('user_id', $userId)->whereRaw('LOWER(title) = ?', [strtolower($columns[2])])->first();

            if ($exercise) {
                $loggedAt = $this->parseDate($columns[0] . ' ' . $columns[1]);

                if (!$loggedAt) {
                    $invalidRows[] = $row;
                    continue;
                }

                $liftLog = \App\Models\LiftLog::create([
                    'user_id' => $userId,
                    'exercise_id' => $exercise->id,
                    'comments' => isset($columns[6]) ? $columns[6] : null,
                    'logged_at' => $loggedAt,
                ]);

                // Create LiftSet records based on rounds
                $weight = $columns[3];
                $reps = $columns[4];
                $rounds = $columns[5];
                $notes = isset($columns[6]) ? $columns[6] : null;

                for ($i = 0; $i < $rounds; $i++) {
                    $liftLog->liftSets()->create([
                        'weight' => $weight,
                        'reps' => $reps,
                        'notes' => $notes,
                    ]);
                }
                $importedCount++;
            } else {
                $notFound[] = $columns[2];
            }
        }

        return [
            'importedCount' => $importedCount,
            'notFound' => $notFound,
            'invalidRows' => $invalidRows,
        ];
    }

    private function parseDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromFormat('m/d/Y H:i', $dateString);
        } catch (\Exception $e) {
            try {
                return \Carbon\Carbon::createFromFormat('Y-m-d H:i', $dateString);
            } catch (\Exception $e) {
                return null;
            }
        }
    }

    public function importIngredients(string $tsvData, int $userId): array
    {
        $expectedHeader = ['Ingredient', 'Amount', 'Type', 'Calories', 'Fat (g)', 'Sodium (mg)', 'Carb (g)', 'Fiber (g)', 'Added Sugar (g)', 'Protein (g)', 'Calcium (mg)', 'Potassium (mg)', 'Caffeine (mg)', 'Iron (mg)', 'Cost ($)'];

        $result = $this->ingredientTsvProcessorService->processTsv(
            $tsvData,
            $expectedHeader,
            function ($rowData) use ($userId) {
                $unit = $this->ingredientTsvProcessorService->getUnitFromAbbreviation($rowData['Type']);

                if (!$unit) {
                    throw new \Exception('Unit not found for abbreviation: ' . $rowData['Type']);
                }

                $ingredient = \App\Models\Ingredient::where('user_id', $userId)->whereRaw('LOWER(name) = ?', [strtolower($rowData['Ingredient'])])->first();

                $data = [
                    'user_id' => $userId,
                    'name' => $rowData['Ingredient'],
                    'base_quantity' => (float)($rowData['Amount'] ?? 1),
                    'base_unit_id' => $unit->id,
                    'protein' => (float)($rowData['Protein (g)'] ?? 0),
                    'carbs' => (float)($rowData['Carb (g)'] ?? 0),
                    'added_sugars' => (float)($rowData['Added Sugar (g)'] ?? 0),
                    'fats' => (float)($rowData['Fat (g)'] ?? 0),
                    'sodium' => (float)($rowData['Sodium (mg)'] ?? 0),
                    'iron' => (float)($rowData['Iron (mg)'] ?? 0),
                    'potassium' => (float)($rowData['Potassium (mg)'] ?? 0),
                    'fiber' => (float)($rowData['Fiber (g)'] ?? 0),
                    'calcium' => (float)($rowData['Calcium (mg)'] ?? 0),
                    'caffeine' => (float)($rowData['Caffeine (mg)'] ?? 0),
                    'cost_per_unit' => (float)(str_replace("$", "", $rowData['Cost ($)']) ?? 0)
                ];

                if ($ingredient) {
                    $ingredient->update($data);
                } else {
                    \App\Models\Ingredient::create($data);
                }
            }
        );

        return [
            'importedCount' => $result['processedCount'],
            'invalidRows' => $result['invalidRows'],
            'error' => $result['errors'][0] ?? null,
        ];
    }

    public function importMeasurements(string $tsvData, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $invalidRows = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', str_getcsv($row, "\t"));

            if (count($columns) < 5) {
                $invalidRows[] = $row;
                continue;
            }

            $measurementType = \App\Models\MeasurementType::firstOrCreate([
                'name' => $columns[2],
                'default_unit' => $columns[4],
            ]);

            $loggedAt = $this->parseDate($columns[0] . ' ' . $columns[1]);

            if (!$loggedAt) {
                $invalidRows[] = $row;
                continue;
            }

            BodyLog::create([
                'user_id' => $userId,
                'measurement_type_id' => $measurementType->id,
                'value' => $columns[3],
                'comments' => $columns[5] ?? null,
                'logged_at' => $loggedAt,
            ]);
            $importedCount++;
        }

        return [
            'importedCount' => $importedCount,
            'invalidRows' => $invalidRows,
        ];
    }
}
