<?php

namespace App\Services;

class RealisticVariationService
{
    /**
     * Add realistic weight variation to simulate daily fluctuations
     */
    public function addWeightVariation(float $baseWeight, float $variationPercent = 2.0): float
    {
        // Generate random variation within the specified percentage
        $variationRange = $baseWeight * ($variationPercent / 100);
        $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
        
        return round($baseWeight + $variation, 1);
    }

    /**
     * Add performance variation to lift data to simulate real workout conditions
     */
    public function addPerformanceVariation(array $liftData, float $variationPercent = 5.0): array
    {
        $variatedData = $liftData;
        
        foreach ($variatedData as $key => $value) {
            if (is_numeric($value) && in_array($key, ['weight', 'reps', 'sets'])) {
                $variationRange = $value * ($variationPercent / 100);
                $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
                
                // Ensure we don't get negative or zero values
                $newValue = max(1, $value + $variation);
                
                // Round appropriately based on the type
                $variatedData[$key] = match($key) {
                    'weight' => round($newValue, 2),
                    'reps', 'sets' => max(1, (int) round($newValue)),
                    default => $newValue
                };
            }
        }

        return $variatedData;
    }

    /**
     * Add missed workouts to schedule to simulate real-life inconsistencies
     */
    public function addMissedWorkouts(array $schedule, float $missRate = 0.05): array
    {
        $modifiedSchedule = [];
        
        foreach ($schedule as $workout) {
            // Randomly determine if this workout is missed
            $isMissed = mt_rand() / mt_getrandmax() < $missRate;
            
            $workout['is_missed'] = $isMissed;
            $modifiedSchedule[] = $workout;
        }

        return $modifiedSchedule;
    }

    /**
     * Add calorie variation to simulate real eating patterns
     */
    public function addCalorieVariation(int $baseCalories, float $variationPercent = 10.0): int
    {
        $variationRange = $baseCalories * ($variationPercent / 100);
        $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
        
        // Ensure calories don't go below a reasonable minimum
        $minCalories = (int) ($baseCalories * 0.7);
        $newCalories = max($minCalories, $baseCalories + $variation);
        
        return (int) round($newCalories);
    }

    /**
     * Add measurement variation to simulate measurement inconsistencies
     */
    public function addMeasurementVariation(float $baseValue, float $variationPercent = 1.0): float
    {
        $variationRange = $baseValue * ($variationPercent / 100);
        $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
        
        return round($baseValue + $variation, 1);
    }

    /**
     * Add timing variation to meal times to simulate real eating patterns
     */
    public function addMealTimeVariation(\Carbon\Carbon $baseMealTime, int $maxMinutesVariation = 30): \Carbon\Carbon
    {
        $variationMinutes = mt_rand(-$maxMinutesVariation, $maxMinutesVariation);
        return $baseMealTime->copy()->addMinutes($variationMinutes);
    }

    /**
     * Simulate occasional "bad" days with higher calorie intake
     */
    public function addCheatDayVariation(array $mealPlan, float $cheatDayProbability = 0.05): array
    {
        $isCheatDay = mt_rand() / mt_getrandmax() < $cheatDayProbability;
        
        if ($isCheatDay) {
            // Increase calories by 20-50% on cheat days
            $multiplier = 1.2 + (mt_rand() / mt_getrandmax() * 0.3);
            
            foreach ($mealPlan as $meal => $data) {
                $mealPlan[$meal]['calories'] = (int) round($data['calories'] * $multiplier);
            }
        }

        return $mealPlan;
    }

    /**
     * Add workout intensity variation based on factors like fatigue, motivation
     */
    public function addWorkoutIntensityVariation(array $workoutData, float $intensityVariation = 0.1): array
    {
        // Generate intensity factor (0.9 to 1.1 for ±10% variation)
        $intensityFactor = 1 + ((mt_rand() / mt_getrandmax() - 0.5) * 2 * $intensityVariation);
        
        $modifiedWorkout = $workoutData;
        
        // Apply intensity variation to relevant metrics
        if (isset($modifiedWorkout['volume'])) {
            $modifiedWorkout['volume'] = round($modifiedWorkout['volume'] * $intensityFactor, 2);
        }
        
        if (isset($modifiedWorkout['duration_minutes'])) {
            $modifiedWorkout['duration_minutes'] = max(15, (int) round($modifiedWorkout['duration_minutes'] * $intensityFactor));
        }

        return $modifiedWorkout;
    }

    /**
     * Simulate plateau periods where progress stalls temporarily
     */
    public function addProgressionPlateau(array $progressionData, float $plateauProbability = 0.15, int $plateauLength = 7): array
    {
        $modifiedProgression = $progressionData;
        $dataLength = count($progressionData);
        
        // Randomly determine if and where plateaus occur
        for ($i = 0; $i < $dataLength; $i++) {
            if (mt_rand() / mt_getrandmax() < $plateauProbability) {
                // Create plateau by keeping values the same for several days
                $plateauEnd = min($i + $plateauLength, $dataLength - 1);
                $plateauValue = $progressionData[$i];
                
                for ($j = $i; $j <= $plateauEnd; $j++) {
                    $modifiedProgression[$j] = $plateauValue;
                }
                
                // Skip ahead to avoid overlapping plateaus
                $i = $plateauEnd;
            }
        }

        return $modifiedProgression;
    }

    /**
     * Add seasonal or weekly patterns to data (e.g., weekend eating patterns)
     */
    public function addWeeklyPattern(\Carbon\Carbon $date, array $baseData, string $dataType = 'calories'): array
    {
        $dayOfWeek = $date->dayOfWeek;
        $modifiedData = $baseData;
        
        // Weekend patterns (Friday, Saturday, Sunday)
        if (in_array($dayOfWeek, [5, 6, 0])) {
            switch ($dataType) {
                case 'calories':
                    // People tend to eat more on weekends
                    foreach ($modifiedData as $meal => $data) {
                        $modifiedData[$meal]['calories'] = (int) round($data['calories'] * 1.15);
                    }
                    break;
                    
                case 'workout_intensity':
                    // Weekend workouts might be longer but less intense
                    if (isset($modifiedData['intensity'])) {
                        $modifiedData['intensity'] *= 0.9;
                    }
                    if (isset($modifiedData['duration_minutes'])) {
                        $modifiedData['duration_minutes'] = (int) round($modifiedData['duration_minutes'] * 1.2);
                    }
                    break;
            }
        }

        return $modifiedData;
    }

    /**
     * Add realistic portion size variations to meal ingredients
     */
    public function addPortionVariation(array $ingredients, float $variationPercent = 15.0): array
    {
        $modifiedIngredients = [];
        
        foreach ($ingredients as $ingredient) {
            $modifiedIngredient = $ingredient;
            
            if (isset($ingredient['quantity'])) {
                $variationRange = $ingredient['quantity'] * ($variationPercent / 100);
                $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
                
                // Ensure quantity doesn't go below 10% of original
                $minQuantity = $ingredient['quantity'] * 0.1;
                $newQuantity = max($minQuantity, $ingredient['quantity'] + $variation);
                
                $modifiedIngredient['quantity'] = round($newQuantity, 1);
                
                // Adjust calories proportionally if present
                if (isset($ingredient['calories'])) {
                    $ratio = $newQuantity / $ingredient['quantity'];
                    $modifiedIngredient['calories'] = (int) round($ingredient['calories'] * $ratio);
                }
            }
            
            $modifiedIngredients[] = $modifiedIngredient;
        }
        
        return $modifiedIngredients;
    }

    /**
     * Simulate occasional meal skipping or replacement
     */
    public function addMealSkippingVariation(array $mealPlan, float $skipProbability = 0.03): array
    {
        $modifiedMealPlan = [];
        
        foreach ($mealPlan as $mealType => $mealData) {
            $isSkipped = mt_rand() / mt_getrandmax() < $skipProbability;
            
            if ($isSkipped && $mealType !== 'dinner') { // Never skip dinner
                // Redistribute calories to other meals
                $skippedCalories = $mealData['calories'];
                $redistributePerMeal = $skippedCalories / (count($mealPlan) - 1);
                
                $modifiedMealPlan[$mealType] = [
                    'calories' => 0,
                    'time' => $mealData['time'],
                    'ingredients' => [],
                    'skipped' => true
                ];
                
                // Add redistributed calories to other meals
                foreach ($mealPlan as $otherMealType => $otherMealData) {
                    if ($otherMealType !== $mealType) {
                        if (!isset($modifiedMealPlan[$otherMealType])) {
                            $modifiedMealPlan[$otherMealType] = $otherMealData;
                        }
                        $modifiedMealPlan[$otherMealType]['calories'] += (int) round($redistributePerMeal);
                    }
                }
            } else {
                $modifiedMealPlan[$mealType] = $mealData;
            }
        }
        
        return $modifiedMealPlan;
    }

    /**
     * Add ingredient substitution variations (e.g., different protein sources)
     */
    public function addIngredientSubstitution(array $ingredients, float $substitutionProbability = 0.1): array
    {
        $substitutions = [
            'Chicken Breast' => ['Turkey Breast', 'Lean Beef', 'Fish'],
            'Rice' => ['Quinoa', 'Sweet Potato', 'Pasta'],
            'Broccoli' => ['Green Beans', 'Asparagus', 'Brussels Sprouts'],
            'Almonds' => ['Walnuts', 'Cashews', 'Peanuts'],
            'Banana' => ['Apple', 'Orange', 'Berries'],
            'Oats' => ['Cereal', 'Granola', 'Toast']
        ];
        
        $modifiedIngredients = [];
        
        foreach ($ingredients as $ingredient) {
            $shouldSubstitute = mt_rand() / mt_getrandmax() < $substitutionProbability;
            
            if ($shouldSubstitute && isset($substitutions[$ingredient['name']])) {
                $alternatives = $substitutions[$ingredient['name']];
                $substitute = $alternatives[array_rand($alternatives)];
                
                $modifiedIngredient = $ingredient;
                $modifiedIngredient['name'] = $substitute;
                $modifiedIngredient['substituted'] = true;
                
                $modifiedIngredients[] = $modifiedIngredient;
            } else {
                $modifiedIngredients[] = $ingredient;
            }
        }
        
        return $modifiedIngredients;
    }

    /**
     * Add realistic eating time variations
     */
    public function addEatingTimeVariation(array $mealPlan, int $maxMinutesVariation = 45): array
    {
        $modifiedMealPlan = [];
        
        foreach ($mealPlan as $mealType => $mealData) {
            $modifiedMeal = $mealData;
            
            if (isset($mealData['time'])) {
                $variationMinutes = mt_rand(-$maxMinutesVariation, $maxMinutesVariation);
                $modifiedMeal['time'] = $mealData['time']->copy()->addMinutes($variationMinutes);
            }
            
            $modifiedMealPlan[$mealType] = $modifiedMeal;
        }
        
        return $modifiedMealPlan;
    }

    /**
     * Apply comprehensive nutrition variations to a day's meal plan
     */
    public function applyNutritionVariations(array $dayNutritionData, \Carbon\Carbon $date): array
    {
        $modifiedData = $dayNutritionData;
        
        // Apply calorie variation to target
        if (isset($modifiedData['target_calories'])) {
            $modifiedData['target_calories'] = $this->addCalorieVariation($modifiedData['target_calories'], 8.0);
        }
        
        // Apply meal-level variations
        if (isset($modifiedData['meals'])) {
            // Add weekly patterns (weekend eating)
            $modifiedData['meals'] = $this->addWeeklyPattern($date, $modifiedData['meals'], 'calories');
            
            // Add meal skipping variation
            $modifiedData['meals'] = $this->addMealSkippingVariation($modifiedData['meals'], 0.02);
            
            // Add eating time variations
            $modifiedData['meals'] = $this->addEatingTimeVariation($modifiedData['meals'], 30);
            
            // Apply ingredient-level variations
            foreach ($modifiedData['meals'] as $mealType => $mealData) {
                if (isset($mealData['ingredients']) && !empty($mealData['ingredients'])) {
                    // Add portion variations
                    $modifiedData['meals'][$mealType]['ingredients'] = $this->addPortionVariation($mealData['ingredients'], 12.0);
                    
                    // Add ingredient substitutions
                    $modifiedData['meals'][$mealType]['ingredients'] = $this->addIngredientSubstitution($modifiedData['meals'][$mealType]['ingredients'], 0.08);
                }
            }
        }
        
        return $modifiedData;
    }

    /**
     * Add body measurement specific variations including water retention effects
     */
    public function addBodyMeasurementVariations(array $measurementData, string $measurementType, \Carbon\Carbon $date): array
    {
        $modifiedData = [];
        
        foreach ($measurementData as $measurement) {
            $modifiedMeasurement = $measurement;
            
            // Apply measurement-specific variations
            switch (strtolower($measurementType)) {
                case 'weight':
                    // Weight fluctuations due to water retention, food intake, etc.
                    $modifiedMeasurement['value'] = $this->addWeightFluctuations($measurement['value'], $date);
                    break;
                    
                case 'waist':
                    // Waist measurements are more stable but can vary with bloating
                    $modifiedMeasurement['value'] = $this->addWaistVariations($measurement['value'], $date);
                    break;
                    
                default:
                    // Generic measurement variation
                    $modifiedMeasurement['value'] = $this->addMeasurementVariation($measurement['value'], 1.0);
                    break;
            }
            
            $modifiedData[] = $modifiedMeasurement;
        }
        
        return $modifiedData;
    }

    /**
     * Add realistic weight fluctuations based on various factors
     */
    public function addWeightFluctuations(float $baseWeight, \Carbon\Carbon $date): float
    {
        $dayOfWeek = $date->dayOfWeek;
        $variation = 0;
        
        // Weekend weight gain (higher sodium, more food)
        if (in_array($dayOfWeek, [0, 1])) { // Sunday, Monday
            $variation += mt_rand(5, 15) / 10; // +0.5 to +1.5 lbs
        }
        
        // Mid-week lowest weights (Tuesday-Thursday)
        if (in_array($dayOfWeek, [2, 3, 4])) {
            $variation -= mt_rand(0, 10) / 10; // -0 to -1.0 lbs
        }
        
        // Add random daily fluctuation
        $dailyVariation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * 1.5; // ±1.5 lbs
        $variation += $dailyVariation;
        
        // Ensure variation doesn't exceed reasonable bounds
        $variation = max(-3, min(3, $variation));
        
        return round($baseWeight + $variation, 1);
    }

    /**
     * Add waist measurement variations (less volatile than weight)
     */
    public function addWaistVariations(float $baseWaist, \Carbon\Carbon $date): float
    {
        $dayOfWeek = $date->dayOfWeek;
        $variation = 0;
        
        // Slight bloating on weekends
        if (in_array($dayOfWeek, [6, 0])) { // Saturday, Sunday
            $variation += mt_rand(0, 3) / 10; // +0 to +0.3 inches
        }
        
        // Add small random variation
        $dailyVariation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * 0.25; // ±0.25 inches
        $variation += $dailyVariation;
        
        // Keep variation within reasonable bounds
        $variation = max(-0.5, min(0.5, $variation));
        
        return round($baseWaist + $variation, 1);
    }

    /**
     * Simulate measurement timing inconsistencies
     */
    public function addMeasurementTimingVariation(\Carbon\Carbon $baseTime, string $measurementType): \Carbon\Carbon
    {
        $maxVariation = match(strtolower($measurementType)) {
            'weight' => 30, // ±30 minutes for weight (morning routine variation)
            'waist' => 60,  // ±60 minutes for waist (more flexible timing)
            default => 45   // Default variation
        };
        
        $variationMinutes = mt_rand(-$maxVariation, $maxVariation);
        return $baseTime->copy()->addMinutes($variationMinutes);
    }

    /**
     * Add "whoosh" effect to weight loss (sudden drops after plateaus)
     */
    public function addWhooshEffect(array $weightProgression, float $whooshProbability = 0.1): array
    {
        $modifiedProgression = $weightProgression;
        $dataLength = count($weightProgression);
        
        for ($i = 7; $i < $dataLength - 7; $i++) { // Start after first week, end before last week
            if (mt_rand() / mt_getrandmax() < $whooshProbability) {
                // Create a whoosh effect: sudden 2-4 lb drop
                $whooshAmount = mt_rand(20, 40) / 10; // 2.0 to 4.0 lbs
                
                // Apply whoosh over 2-3 days
                $whooshDays = mt_rand(2, 3);
                $dailyWhoosh = $whooshAmount / $whooshDays;
                
                for ($j = 0; $j < $whooshDays && ($i + $j) < $dataLength; $j++) {
                    $modifiedProgression[$i + $j] -= $dailyWhoosh * ($j + 1);
                }
                
                // Skip ahead to avoid overlapping whooshes
                $i += $whooshDays + 7; // Wait at least a week before next possible whoosh
            }
        }
        
        return $modifiedProgression;
    }

    /**
     * Add measurement consistency patterns (some people measure more consistently)
     */
    public function addMeasurementConsistencyPattern(array $measurementSchedule, float $consistencyLevel = 0.8): array
    {
        $modifiedSchedule = [];
        
        foreach ($measurementSchedule as $measurement) {
            $modifiedMeasurement = $measurement;
            
            // Randomly skip measurements based on consistency level
            $shouldSkip = mt_rand() / mt_getrandmax() > $consistencyLevel;
            
            if ($shouldSkip) {
                $modifiedMeasurement['skipped'] = true;
                $modifiedMeasurement['skip_reason'] = $this->getSkipReason();
            }
            
            $modifiedSchedule[] = $modifiedMeasurement;
        }
        
        return $modifiedSchedule;
    }

    /**
     * Add measurement gaps to create realistic measurement inconsistency
     */
    public function addMeasurementGaps(array $schedule, float $gapRate = 0.15): array
    {
        $modifiedSchedule = [];
        $skipNext = false;
        $gapLength = 0;
        
        foreach ($schedule as $index => $measurement) {
            // Randomly start a gap
            if (!$skipNext && mt_rand() / mt_getrandmax() < $gapRate) {
                $skipNext = true;
                $gapLength = mt_rand(3, 10); // Gap of 3-10 days
            }
            
            if ($skipNext && $gapLength > 0) {
                // Skip this measurement
                $gapLength--;
                if ($gapLength <= 0) {
                    $skipNext = false;
                }
                continue;
            }
            
            $modifiedSchedule[] = $measurement;
        }
        
        return $modifiedSchedule;
    }

    /**
     * Simulate whoosh effect for sudden weight drops after plateaus
     */
    public function simulateWhooshEffect(array $weightData, float $probability = 0.2): array
    {
        return $this->addWhooshEffect($weightData, $probability);
    }

    /**
     * Add plateau periods for realistic weight loss stalls
     */
    public function addPlateauPeriods(array $progressionData, int $plateauDays = 10): array
    {
        return $this->addProgressionPlateau($progressionData, 0.2, $plateauDays);
    }

    /**
     * Add measurement precision variations for different measurement types
     */
    public function addMeasurementPrecisionVariation(float $baseValue, string $measurementType): float
    {
        $precisionVariations = [
            'weight' => 0.2,      // ±0.2 lbs (scale precision)
            'waist' => 0.125,     // ±1/8 inch (tape measure precision)
            'body_fat' => 0.5,    // ±0.5% (body fat scale precision)
            'muscle_mass' => 0.3, // ±0.3 lbs (bioelectrical impedance variation)
            'chest' => 0.125,     // ±1/8 inch
            'arm' => 0.125,       // ±1/8 inch
            'thigh' => 0.125,     // ±1/8 inch
        ];
        
        $variation = $precisionVariations[strtolower($measurementType)] ?? 0.1;
        $randomVariation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variation;
        
        return round($baseValue + $randomVariation, 1);
    }

    /**
     * Add body fat specific variations
     */
    public function addBodyFatVariation(float $baseBF, float $variationPercent = 3.0): float
    {
        // Body fat measurements are notoriously inconsistent
        $variationRange = $baseBF * ($variationPercent / 100);
        $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
        
        // Ensure body fat doesn't go below 5% or above 50%
        $newBF = max(5.0, min(50.0, $baseBF + $variation));
        
        return round($newBF, 1);
    }

    /**
     * Add muscle mass specific variations
     */
    public function addMuscleMassVariation(float $baseMass, float $variationPercent = 2.0): float
    {
        // Muscle mass measurements via bioelectrical impedance are variable
        $variationRange = $baseMass * ($variationPercent / 100);
        $variation = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $variationRange;
        
        // Ensure muscle mass stays within reasonable bounds
        $newMass = max($baseMass * 0.8, min($baseMass * 1.2, $baseMass + $variation));
        
        return round($newMass, 1);
    }

    /**
     * Get random reason for skipping a measurement
     */
    private function getSkipReason(): string
    {
        $reasons = [
            'Forgot to measure',
            'Running late',
            'Scale not available',
            'Traveling',
            'Sick day',
            'Overslept',
            'Weekend laziness'
        ];
        
        return $reasons[array_rand($reasons)];
    }
}