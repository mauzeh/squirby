<?php

namespace App\Services;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\BodyLog;
use App\Models\Exercise;
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

                // Check for existing FoodLog entry with the same ingredient, logged_at, and quantity
                $existingFoodLog = FoodLog::where('user_id', $userId)
                    ->where('ingredient_id', $ingredient->id)
                    ->where('logged_at', $loggedAt)
                    ->where('quantity', $columns[4])
                    ->first();

                if ($existingFoodLog) {
                    // Skip this row as it's a duplicate
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
        $updatedCount = 0;
        $notFound = [];
        $invalidRows = [];
        $importedEntries = [];
        $updatedEntries = [];

        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            $columns = array_map('trim', explode("\t", $row));

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

                // Extract LiftSet details from TSV
                $weight = $columns[3];
                $reps = $columns[4];
                $rounds = $columns[5];
                $notes = $columns[6] ?? '';

                $entryDescription = $exercise->title . ' on ' . $loggedAt->format('m/d/Y H:i') . ' (' . $weight . 'lbs x ' . $reps . ' reps x ' . $rounds . ' sets)';

                // Check for existing LiftLog entries with the same user, exercise, and logged_at
                $existingLiftLogs = \App\Models\LiftLog::with('liftSets')
                    ->where('user_id', $userId)
                    ->where('exercise_id', $exercise->id)
                    ->where('logged_at', $loggedAt->format('Y-m-d H:i:s'))
                    ->get();

                $shouldUpdate = false;
                $isDuplicate = false;
                $matchingLiftLog = null;

                foreach ($existingLiftLogs as $existingLiftLog) {
                    // Check if the sets match (same count, weight, and reps)
                    if ($existingLiftLog->liftSets->count() === (int)$rounds) {
                        $allSetsMatch = true;
                        foreach ($existingLiftLog->liftSets as $set) {
                            if (!($set->weight == $weight && $set->reps == $reps)) {
                                $allSetsMatch = false;
                                break;
                            }
                        }
                        
                        if ($allSetsMatch) {
                            $matchingLiftLog = $existingLiftLog;
                            if ($existingLiftLog->comments === $notes) {
                                // Exact duplicate - skip it
                                $isDuplicate = true;
                                break;
                            } else {
                                // Same sets but different comments - update it
                                $shouldUpdate = true;
                                break;
                            }
                        }
                    }
                }

                if ($isDuplicate) {
                    continue; // Skip exact duplicates
                }

                if ($shouldUpdate) {
                    // Update existing lift log with new comments
                    $matchingLiftLog->update([
                        'comments' => $notes,
                    ]);
                    
                    // Also update the notes on all lift sets
                    foreach ($matchingLiftLog->liftSets as $set) {
                        $set->update(['notes' => $notes]);
                    }
                    
                    $updatedCount++;
                    $updatedEntries[] = $entryDescription;
                } else {
                    // Create new lift log (different data or no existing match)
                    $liftLog = \App\Models\LiftLog::create([
                        'user_id' => $userId,
                        'exercise_id' => $exercise->id,
                        'comments' => $notes,
                        'logged_at' => $loggedAt,
                    ]);

                    for ($i = 0; $i < $rounds; $i++) {
                        $liftLog->liftSets()->create([
                            'weight' => $weight,
                            'reps' => $reps,
                            'notes' => $notes,
                        ]);
                    }
                    $importedCount++;
                    $importedEntries[] = $entryDescription;
                }
            } else {
                $notFound[] = $columns[2];
            }
        }

        return [
            'importedCount' => $importedCount,
            'updatedCount' => $updatedCount,
            'notFound' => $notFound,
            'invalidRows' => $invalidRows,
            'importedEntries' => $importedEntries,
            'updatedEntries' => $updatedEntries,
        ];
    }

    private function parseDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::createFromFormat('m/d/Y g:i A', $dateString);
        } catch (\Exception $e) {
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
    }

    public function importIngredients(string $tsvData, int $userId): array
    {
        $expectedHeader = [
            'Ingredient',
            'Amount',
            'Type',
            'Calories',
            'Fat (g)',
            'Sodium (mg)',
            'Carb (g)',
            'Fiber (g)',
            'Added Sugar (g)',
            'Protein (g)',
            'Calcium (mg)',
            'Potassium (mg)',
            'Caffeine (mg)',
            'Iron (mg)',
            'Cost ($)'
        ];

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

    public function importExercises(string $tsvData, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $invalidRows = [];
        $updatedCount = 0;

        foreach ($rows as $row) {
            if (empty(trim($row))) {
                continue;
            }

            $columns = array_map('trim', explode("\t", $row));

            if (count($columns) < 2 || empty($columns[0])) {
                $invalidRows[] = $row;
                continue;
            }

            $title = $columns[0];
            $description = $columns[1] ?? '';
            
            // Parse boolean value - handle various formats
            $isBodyweight = false;
            if (isset($columns[2])) {
                $boolValue = strtolower(trim($columns[2]));
                $isBodyweight = in_array($boolValue, ['true', '1', 'yes', 'y']);
            }

            // Check if exercise already exists for this user
            $existingExercise = Exercise::where('user_id', $userId)
                ->whereRaw('LOWER(title) = ?', [strtolower($title)])
                ->first();

            if ($existingExercise) {
                // Check if data actually differs before updating
                if ($existingExercise->description !== $description || $existingExercise->is_bodyweight !== $isBodyweight) {
                    // Update existing exercise only if data differs
                    $existingExercise->update([
                        'description' => $description,
                        'is_bodyweight' => $isBodyweight,
                    ]);
                    $updatedCount++;
                }
                // If data is the same, we skip it (no increment to any counter)
            } else {
                // Create new exercise
                Exercise::create([
                    'user_id' => $userId,
                    'title' => $title,
                    'description' => $description,
                    'is_bodyweight' => $isBodyweight,
                ]);
                $importedCount++;
            }
        }

        return [
            'importedCount' => $importedCount,
            'updatedCount' => $updatedCount,
            'invalidRows' => $invalidRows,
        ];
    }
}
