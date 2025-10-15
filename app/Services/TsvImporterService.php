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

            $exercise = \App\Models\Exercise::availableToUser($userId)->whereRaw('LOWER(title) = ?', [strtolower($columns[2])])->first();

            if ($exercise) {
                $loggedAt = $this->parseDate($columns[0] . ' ' . $columns[1]);

                if (!$loggedAt) {
                    $invalidRows[] = $row;
                    continue;
                }

                // Round time to nearest 15-minute interval for consistency
                $minutes = $loggedAt->minute;
                $remainder = $minutes % 15;
                if ($remainder !== 0) {
                    $loggedAt->addMinutes(15 - $remainder);
                }

                // Extract LiftSet details from TSV
                $weight = $columns[3];
                $reps = $columns[4];
                $rounds = $columns[5];
                $notes = $columns[6] ?? '';
                $bandColor = $columns[7] ?? 'none';

                // Validate band_color and cross-validate with exercise band_type
                $bandColorValidation = $this->validateBandColorForExercise($bandColor, $exercise);
                if (!$bandColorValidation['valid']) {
                    $invalidRows[] = $row . " - " . $bandColorValidation['error'];
                    continue;
                }

                // Normalize band_color for database storage
                $normalizedBandColor = $bandColorValidation['normalized_value'];

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
                    // Check if the sets match (same count, weight, reps, and band_color)
                    if ($existingLiftLog->liftSets->count() === (int)$rounds) {
                        $allSetsMatch = true;
                        foreach ($existingLiftLog->liftSets as $set) {
                            if (!($set->weight == $weight && $set->reps == $reps && $set->band_color == $normalizedBandColor)) {
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
                            'band_color' => $normalizedBandColor,
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

        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $importedIngredients = [];
        $updatedIngredients = [];
        $skippedIngredients = [];

        $result = $this->ingredientTsvProcessorService->processTsv(
            $tsvData,
            $expectedHeader,
            function ($rowData) use ($userId, &$importedCount, &$updatedCount, &$skippedCount, &$importedIngredients, &$updatedIngredients, &$skippedIngredients) {
                try {
                    $processResult = $this->processIngredientImport($rowData, $userId);
                    
                    switch ($processResult['action']) {
                        case 'imported':
                            $importedCount++;
                            $importedIngredients[] = [
                                'name' => $processResult['ingredient']->name,
                                'base_quantity' => $processResult['ingredient']->base_quantity,
                                'unit_abbreviation' => $processResult['ingredient']->baseUnit ? $processResult['ingredient']->baseUnit->abbreviation : 'pc'
                            ];
                            break;
                        case 'updated':
                            $updatedCount++;
                            $updatedIngredients[] = [
                                'name' => $processResult['ingredient']->name,
                                'base_quantity' => $processResult['ingredient']->base_quantity,
                                'unit_abbreviation' => $processResult['ingredient']->baseUnit ? $processResult['ingredient']->baseUnit->abbreviation : 'pc',
                                'changes' => $processResult['changes'] ?? []
                            ];
                            break;
                        case 'skipped':
                            $skippedCount++;
                            $skippedIngredients[] = [
                                'name' => $processResult['name'],
                                'reason' => $processResult['reason']
                            ];
                            break;
                    }
                } catch (\Exception $e) {
                    // Re-throw to be caught by the processor's exception handling
                    throw $e;
                }
            }
        );

        // Handle errors from TSV processing
        if (!empty($result['errors'])) {
            return [
                'importedCount' => 0,
                'updatedCount' => 0,
                'skippedCount' => 0,
                'invalidRows' => $result['invalidRows'],
                'importedIngredients' => [],
                'updatedIngredients' => [],
                'skippedIngredients' => [],
                'importMode' => 'personal',
                'error' => $result['errors'][0]
            ];
        }

        return [
            'importedCount' => $importedCount,
            'updatedCount' => $updatedCount,
            'skippedCount' => $skippedCount,
            'invalidRows' => $result['invalidRows'],
            'importedIngredients' => $importedIngredients,
            'updatedIngredients' => $updatedIngredients,
            'skippedIngredients' => $skippedIngredients,
            'importMode' => 'personal'
        ];
    }

    public function importMeasurements(string $tsvData, int $userId): array
    {
        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $invalidRows = [];
        $importedEntries = [];

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

            // Check for existing BodyLog entry with the same data
            $existingBodyLog = BodyLog::where('user_id', $userId)
                ->where('measurement_type_id', $measurementType->id)
                ->where('logged_at', $loggedAt)
                ->where('value', $columns[3])
                ->where('comments', $columns[5] ?? null)
                ->first();

            if (!$existingBodyLog) {
                BodyLog::create([
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementType->id,
                    'value' => $columns[3],
                    'comments' => $columns[5] ?? null,
                    'logged_at' => $loggedAt,
                ]);
                
                $entryDescription = $measurementType->name . ' on ' . $loggedAt->format('m/d/Y H:i') . ' (' . $columns[3] . ' ' . $measurementType->default_unit . ')';
                $importedEntries[] = $entryDescription;
                $importedCount++;
            }
            // If it already exists, we skip it (no increment to importedCount)
        }

        return [
            'importedCount' => $importedCount,
            'invalidRows' => $invalidRows,
            'importedEntries' => $importedEntries,
        ];
    }

    public function importExercises(string $tsvData, int $userId, bool $importAsGlobal = false): array
    {
        // Validate admin permission for global imports
        if ($importAsGlobal) {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->hasRole('Admin')) {
                throw new \Exception('Only administrators can import global exercises.');
            }
        }

        $rows = explode("\n", $tsvData);
        $importedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $invalidRows = [];
        $importedExercises = [];
        $updatedExercises = [];
        $skippedExercises = [];

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
            $isBodyweight = $this->parseBooleanValue($columns[2] ?? 'false');
            $bandType = $columns[3] ?? 'none';

            // Validate band_type
            if (!$this->isValidBandType($bandType)) {
                $invalidRows[] = $row . " - " . $this->getInvalidBandTypeErrorMessage($bandType);
                continue;
            }

            // Normalize and convert band_type for database storage
            $normalizedBandType = strtolower(trim($bandType));
            $bandTypeValue = $normalizedBandType === 'none' ? null : $normalizedBandType;

            $result = $this->processExerciseImport($title, $description, $isBodyweight, $bandTypeValue, $userId, $importAsGlobal);
            
            switch ($result['action']) {
                case 'imported':
                    $importedCount++;
                    $importedExercises[] = [
                        'title' => $result['exercise']->title,
                        'description' => $result['exercise']->description,
                        'is_bodyweight' => $result['exercise']->is_bodyweight,
                        'band_type' => $result['exercise']->band_type,
                        'type' => $result['exercise']->isGlobal() ? 'global' : 'personal'
                    ];
                    break;
                case 'updated':
                    $updatedCount++;
                    $updatedExercises[] = [
                        'title' => $result['exercise']->title,
                        'description' => $result['exercise']->description,
                        'is_bodyweight' => $result['exercise']->is_bodyweight,
                        'band_type' => $result['exercise']->band_type,
                        'type' => $result['exercise']->isGlobal() ? 'global' : 'personal',
                        'changes' => $result['changes'] ?? []
                    ];
                    break;
                case 'skipped':
                    $skippedCount++;
                    $skippedExercises[] = [
                        'title' => $title,
                        'reason' => $result['reason']
                    ];
                    break;
            }
        }

        return [
            'importedCount' => $importedCount,
            'updatedCount' => $updatedCount,
            'skippedCount' => $skippedCount,
            'invalidRows' => $invalidRows,
            'importedExercises' => $importedExercises,
            'updatedExercises' => $updatedExercises,
            'skippedExercises' => $skippedExercises,
            'importMode' => $importAsGlobal ? 'global' : 'personal'
        ];
    }

    private function processExerciseImport(string $title, string $description, bool $isBodyweight, ?string $bandType, int $userId, bool $importAsGlobal): array
    {
        if ($importAsGlobal) {
            return $this->processGlobalExerciseImport($title, $description, $isBodyweight, $bandType);
        } else {
            return $this->processUserExerciseImport($title, $description, $isBodyweight, $bandType, $userId);
        }
    }

    private function processGlobalExerciseImport(string $title, string $description, bool $isBodyweight, ?string $bandType): array
    {
        // Check for existing global exercise
        $existingGlobal = Exercise::global()
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($existingGlobal) {
            // Check if data differs
            $changes = [];
            if ($existingGlobal->description !== $description) {
                $changes['description'] = ['from' => $existingGlobal->description, 'to' => $description];
            }
            if ($existingGlobal->is_bodyweight !== $isBodyweight) {
                $changes['is_bodyweight'] = ['from' => $existingGlobal->is_bodyweight, 'to' => $isBodyweight];
            }
            if ($existingGlobal->band_type !== $bandType) {
                $changes['band_type'] = ['from' => $existingGlobal->band_type, 'to' => $bandType];
            }
            
            if (!empty($changes)) {
                $existingGlobal->update([
                    'description' => $description,
                    'is_bodyweight' => $isBodyweight,
                    'band_type' => $bandType,
                ]);
                return ['action' => 'updated', 'exercise' => $existingGlobal, 'changes' => $changes];
            } else {
                return ['action' => 'skipped', 'reason' => "Global exercise '{$title}' already exists with same data"];
            }
        }

        // Check for any user exercise conflict
        $userConflict = Exercise::whereNotNull('user_id')
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($userConflict) {
            return ['action' => 'skipped', 'reason' => "Exercise '{$title}' conflicts with existing user exercise"];
        }

        // Create new global exercise
        $exercise = Exercise::create([
            'user_id' => null, // Global exercise
            'title' => $title,
            'description' => $description,
            'is_bodyweight' => $isBodyweight,
            'band_type' => $bandType,
        ]);

        return ['action' => 'imported', 'exercise' => $exercise];
    }

    private function processUserExerciseImport(string $title, string $description, bool $isBodyweight, ?string $bandType, int $userId): array
    {
        // Check for global exercise conflict first
        $globalConflict = Exercise::global()
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($globalConflict) {
            return ['action' => 'skipped', 'reason' => "Exercise '{$title}' conflicts with existing global exercise"];
        }

        // Check for existing user exercise
        $existingUser = Exercise::userSpecific($userId)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->first();

        if ($existingUser) {
            // Check if data differs
            $changes = [];
            if ($existingUser->description !== $description) {
                $changes['description'] = ['from' => $existingUser->description, 'to' => $description];
            }
            if ($existingUser->is_bodyweight !== $isBodyweight) {
                $changes['is_bodyweight'] = ['from' => $existingUser->is_bodyweight, 'to' => $isBodyweight];
            }
            if ($existingUser->band_type !== $bandType) {
                $changes['band_type'] = ['from' => $existingUser->band_type, 'to' => $bandType];
            }
            
            if (!empty($changes)) {
                $existingUser->update([
                    'description' => $description,
                    'is_bodyweight' => $isBodyweight,
                    'band_type' => $bandType,
                ]);
                return ['action' => 'updated', 'exercise' => $existingUser, 'changes' => $changes];
            } else {
                return ['action' => 'skipped', 'reason' => "Personal exercise '{$title}' already exists with same data"];
            }
        }

        // Create new user exercise
        $exercise = Exercise::create([
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'is_bodyweight' => $isBodyweight,
            'band_type' => $bandType,
        ]);

        return ['action' => 'imported', 'exercise' => $exercise];
    }

    private function processIngredientImport(array $rowData, int $userId): array
    {
        $ingredientName = $rowData['Ingredient'];
        
        // Get unit for the ingredient
        $unit = $this->ingredientTsvProcessorService->getUnitFromAbbreviation($rowData['Type']);
        
        if (!$unit) {
            throw new \Exception('Unit not found for abbreviation: ' . $rowData['Type']);
        }
        
        // Case-insensitive lookup for existing ingredient (personal only)
        $existingIngredient = Ingredient::where('user_id', $userId)
            ->whereRaw('LOWER(name) = ?', [strtolower($ingredientName)])
            ->first();
        
        // Prepare ingredient data (excluding user_id for comparison)
        $ingredientData = [
            'name' => $ingredientName,
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
        
        if ($existingIngredient) {
            // Preserve the existing name case if it's just a case difference
            if (strtolower($existingIngredient->name) === strtolower($ingredientName)) {
                $ingredientData['name'] = $existingIngredient->name;
            }
        }
        
        if ($existingIngredient) {
            // Check if data differs
            $changes = $this->detectIngredientChanges($existingIngredient, $ingredientData);
            
            if (!empty($changes)) {
                $existingIngredient->update($ingredientData);
                return [
                    'action' => 'updated',
                    'ingredient' => $existingIngredient->fresh()->load('baseUnit'),
                    'changes' => $changes
                ];
            } else {
                return [
                    'action' => 'skipped',
                    'name' => $ingredientName,
                    'reason' => "Ingredient '{$ingredientName}' already exists with same data"
                ];
            }
        }
        
        // Create new ingredient (add user_id for creation)
        $ingredientData['user_id'] = $userId;
        $ingredient = Ingredient::create($ingredientData);
        return [
            'action' => 'imported',
            'ingredient' => $ingredient->load('baseUnit')
        ];
    }

    private function detectIngredientChanges(Ingredient $existing, array $newData): array
    {
        $changes = [];
        $trackableFields = [
            'name', 'base_quantity', 'base_unit_id', 'protein', 'carbs', 'added_sugars', 
            'fats', 'sodium', 'iron', 'potassium', 'fiber', 'calcium',
            'caffeine', 'cost_per_unit'
        ];
        
        foreach ($trackableFields as $field) {
            if (isset($newData[$field]) && $existing->$field != $newData[$field]) {
                if ($field === 'base_unit_id') {
                    // Handle unit changes with unit names instead of IDs
                    $fromUnit = $existing->baseUnit ? $existing->baseUnit->abbreviation : 'unknown';
                    $toUnit = \App\Models\Unit::find($newData[$field]);
                    $toUnitName = $toUnit ? $toUnit->abbreviation : 'unknown';
                    $changes[$field] = [
                        'from' => $fromUnit,
                        'to' => $toUnitName
                    ];
                } else {
                    $changes[$field] = [
                        'from' => $existing->$field,
                        'to' => $newData[$field]
                    ];
                }
            }
        }
        
        return $changes;
    }

    private function parseBooleanValue(string $value): bool
    {
        $boolValue = strtolower(trim($value));
        return in_array($boolValue, ['true', '1', 'yes', 'y']);
    }

    private function isValidBandType(string $bandType): bool
    {
        $validBandTypes = ['resistance', 'assistance', 'none'];
        return in_array(strtolower(trim($bandType)), $validBandTypes);
    }

    private function getInvalidBandTypeErrorMessage(string $bandType): string
    {
        return "Invalid band type '{$bandType}' - must be 'resistance', 'assistance', or 'none'";
    }

    private function validateBandColorForExercise(string $bandColor, \App\Models\Exercise $exercise): array
    {
        $normalizedBandColor = strtolower(trim($bandColor));
        $isBandedExercise = in_array($exercise->band_type, ['resistance', 'assistance']);
        
        // Get valid band colors from config
        $validBandColors = $this->getValidBandColors();
        
        if ($normalizedBandColor === 'none') {
            if ($isBandedExercise) {
                return [
                    'valid' => false,
                    'error' => $this->getBandColorValidationErrorMessage('none', $exercise, 'banded_exercise_requires_color'),
                    'normalized_value' => null
                ];
            }
            return [
                'valid' => true,
                'normalized_value' => null
            ];
        }
        
        if (!in_array($normalizedBandColor, $validBandColors)) {
            return [
                'valid' => false,
                'error' => $this->getBandColorValidationErrorMessage($bandColor, $exercise, 'invalid_color', $validBandColors),
                'normalized_value' => null
            ];
        }
        
        if (!$isBandedExercise) {
            return [
                'valid' => false,
                'error' => $this->getBandColorValidationErrorMessage($bandColor, $exercise, 'non_banded_exercise_requires_none'),
                'normalized_value' => null
            ];
        }
        
        return [
            'valid' => true,
            'normalized_value' => $normalizedBandColor
        ];
    }

    private function getBandColorValidationErrorMessage(string $bandColor, \App\Models\Exercise $exercise, string $errorType, array $validColors = []): string
    {
        switch ($errorType) {
            case 'banded_exercise_requires_color':
                return "Invalid band color 'none' for banded exercise '{$exercise->title}' - must be a valid band color";
            
            case 'non_banded_exercise_requires_none':
                return "Invalid band color '{$bandColor}' for non-banded exercise '{$exercise->title}' - must be 'none'";
            
            case 'invalid_color':
                $validColorsString = implode(', ', $validColors);
                return "Invalid band color '{$bandColor}' - must be one of: {$validColorsString}, none";
            
            default:
                return "Invalid band color '{$bandColor}' for exercise '{$exercise->title}'";
        }
    }

    private function getValidBandColors(): array
    {
        return array_keys(config('bands.colors', []));
    }

    private function isValidBandColor(string $bandColor): bool
    {
        $normalizedBandColor = strtolower(trim($bandColor));
        
        if ($normalizedBandColor === 'none') {
            return true;
        }
        
        $validBandColors = $this->getValidBandColors();
        return in_array($normalizedBandColor, $validBandColors);
    }
}
