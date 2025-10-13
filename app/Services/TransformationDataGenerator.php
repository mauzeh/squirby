<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Ingredient;
use App\Models\Unit;

class TransformationDataGenerator
{
    /**
     * Calculate strength progression over time for different exercise types
     */
    public function calculateStrengthProgression(float $startWeight, int $weeks, string $exerciseType): array
    {
        $progression = [];
        $currentWeight = $startWeight;
        
        // Different progression frequencies based on exercise type (weeks between increases)
        $progressionFrequency = match($exerciseType) {
            'squat' => 1,          // 5 lbs every week
            'bench' => 1,          // 5 lbs every week  
            'deadlift' => 1,       // 5 lbs every week
            'overhead_press' => 2, // 5 lbs every 2 weeks (slower progression)
            'accessory' => 2,      // 5 lbs every 2 weeks
            default => 1           // Default every week
        };

        for ($week = 0; $week < $weeks; $week++) {
            $progression[$week] = $currentWeight;
            
            // Check if it's time to increase weight (every N weeks based on exercise type)
            if (($week + 1) % $progressionFrequency === 0) {
                // Apply diminishing returns - progression slows over time
                $weeksProgressed = floor(($week + 1) / $progressionFrequency);
                
                // Start with 5 lb increases, but reduce frequency as weeks progress
                if ($weeksProgressed <= 8) {
                    $currentWeight += 5; // First 8 progression cycles: +5 lbs
                } elseif ($weeksProgressed <= 16) {
                    // After 8 cycles, only increase every other cycle
                    if ($weeksProgressed % 2 === 0) {
                        $currentWeight += 5;
                    }
                } else {
                    // After 16 cycles, only increase every third cycle (plateau/deload)
                    if ($weeksProgressed % 3 === 0) {
                        $currentWeight += 5;
                    }
                }
            }
        }

        return $progression;
    }

    /**
     * Calculate realistic weight loss progression with daily fluctuations
     */
    public function calculateWeightLossProgression(float $startWeight, float $targetWeight, int $days): array
    {
        $progression = [];
        $totalWeightLoss = $startWeight - $targetWeight;
        $dailyWeightLoss = $totalWeightLoss / $days;
        
        $currentWeight = $startWeight;
        
        for ($day = 0; $day < $days; $day++) {
            // Apply weight loss with some non-linear progression
            $progressRatio = $day / $days;
            
            // Weight loss is faster initially, then slows down
            $lossMultiplier = 1.2 - ($progressRatio * 0.4);
            $adjustedDailyLoss = $dailyWeightLoss * $lossMultiplier;
            
            $currentWeight -= $adjustedDailyLoss;
            
            // Ensure we don't go below target weight
            $currentWeight = max($currentWeight, $targetWeight);
            
            $progression[$day] = round($currentWeight, 1);
        }

        return $progression;
    }

    /**
     * Calculate waist measurement progression correlated with weight loss
     */
    public function calculateWaistProgression(float $startWaist, float $weightLossRatio, int $days): array
    {
        $progression = [];
        
        // Waist typically reduces at about 60-80% the rate of weight loss
        $waistReductionRate = 0.7;
        $totalWaistReduction = $startWaist * $weightLossRatio * $waistReductionRate;
        $dailyWaistReduction = $totalWaistReduction / $days;
        
        $currentWaist = $startWaist;
        
        for ($day = 0; $day < $days; $day++) {
            // Waist reduction often lags behind weight loss
            $lagFactor = min(1.0, ($day + 14) / $days); // 2-week lag
            $adjustedReduction = $dailyWaistReduction * $lagFactor;
            
            $currentWaist -= $adjustedReduction;
            $progression[$day] = round($currentWaist, 1);
        }

        return $progression;
    }

    /**
     * Generate workout schedule with rest days
     */
    public function generateWorkoutSchedule(Carbon $startDate, int $weeks): array
    {
        $schedule = [];
        $currentDate = $startDate->copy();
        
        // Typical 4-day split: Mon, Tue, Thu, Fri
        $workoutDays = [1, 2, 4, 5]; // Monday = 1, Sunday = 0
        
        for ($week = 0; $week < $weeks; $week++) {
            $weekStart = $currentDate->copy()->addWeeks($week)->startOfWeek();
            
            foreach ($workoutDays as $dayOfWeek) {
                $workoutDate = $weekStart->copy()->addDays($dayOfWeek - 1);
                $schedule[] = [
                    'date' => $workoutDate,
                    'week' => $week + 1,
                    'day_of_week' => $dayOfWeek,
                    'workout_type' => $this->getWorkoutType($dayOfWeek)
                ];
            }
        }

        return $schedule;
    }

    /**
     * Calculate daily calories based on current weight and goals
     */
    public function calculateDailyCalories(float $currentWeight, string $goal = 'weight_loss'): int
    {
        // Basic TDEE calculation (simplified)
        // BMR using Mifflin-St Jeor equation (assuming male, age 30, height 70 inches)
        $bmr = (10 * ($currentWeight * 0.453592)) + (6.25 * (70 * 2.54)) - (5 * 30) + 5;
        
        // Activity factor for moderate exercise
        $tdee = $bmr * 1.55;
        
        return match($goal) {
            'weight_loss' => (int) round($tdee - 400), // 400 calorie deficit
            'maintenance' => (int) round($tdee),
            'muscle_gain' => (int) round($tdee + 300), // 300 calorie surplus
            default => (int) round($tdee - 400)
        };
    }

    /**
     * Generate meal plan distribution for target calories
     */
    public function generateMealPlan(int $targetCalories, Carbon $date): array
    {
        // Meal distribution percentages
        $distribution = [
            'breakfast' => 0.25,
            'lunch' => 0.35,
            'dinner' => 0.30,
            'snacks' => 0.10
        ];

        $mealPlan = [];
        foreach ($distribution as $meal => $percentage) {
            $mealPlan[$meal] = [
                'calories' => (int) round($targetCalories * $percentage),
                'time' => $this->getMealTime($meal, $date),
                'ingredients' => $this->generateMealIngredients($meal, (int) round($targetCalories * $percentage))
            ];
        }

        return $mealPlan;
    }

    /**
     * Generate realistic meal ingredients for a given meal and calorie target
     */
    public function generateMealIngredients(string $mealType, int $targetCalories): array
    {
        $ingredients = [];
        
        // Define ingredient templates for different meal types
        $mealTemplates = $this->getMealTemplates();
        
        if (!isset($mealTemplates[$mealType])) {
            return $ingredients;
        }
        
        $template = $mealTemplates[$mealType];
        $remainingCalories = $targetCalories;
        
        foreach ($template as $ingredientData) {
            $calorieAllocation = (int) round($targetCalories * $ingredientData['percentage']);
            $quantity = $this->calculateIngredientQuantity($ingredientData['name'], $calorieAllocation);
            
            if ($quantity > 0) {
                $ingredients[] = [
                    'name' => $ingredientData['name'],
                    'quantity' => $quantity,
                    'unit' => $ingredientData['unit'],
                    'calories' => $calorieAllocation
                ];
                $remainingCalories -= $calorieAllocation;
            }
        }
        
        return $ingredients;
    }

    /**
     * Get meal templates with typical ingredient distributions
     */
    private function getMealTemplates(): array
    {
        return [
            'breakfast' => [
                ['name' => 'Oats', 'percentage' => 0.4, 'unit' => 'g'],
                ['name' => 'Banana', 'percentage' => 0.25, 'unit' => 'pc'],
                ['name' => 'Milk', 'percentage' => 0.25, 'unit' => 'ml'],
                ['name' => 'Honey', 'percentage' => 0.1, 'unit' => 'tbsp']
            ],
            'lunch' => [
                ['name' => 'Chicken Breast', 'percentage' => 0.35, 'unit' => 'g'],
                ['name' => 'Rice', 'percentage' => 0.3, 'unit' => 'g'],
                ['name' => 'Broccoli', 'percentage' => 0.2, 'unit' => 'g'],
                ['name' => 'Olive Oil', 'percentage' => 0.15, 'unit' => 'tbsp']
            ],
            'dinner' => [
                ['name' => 'Salmon', 'percentage' => 0.4, 'unit' => 'g'],
                ['name' => 'Sweet Potato', 'percentage' => 0.3, 'unit' => 'g'],
                ['name' => 'Spinach', 'percentage' => 0.15, 'unit' => 'g'],
                ['name' => 'Avocado', 'percentage' => 0.15, 'unit' => 'g']
            ],
            'snacks' => [
                ['name' => 'Apple', 'percentage' => 0.6, 'unit' => 'pc'],
                ['name' => 'Almonds', 'percentage' => 0.4, 'unit' => 'g']
            ]
        ];
    }

    /**
     * Calculate ingredient quantity based on calorie target
     */
    private function calculateIngredientQuantity(string $ingredientName, int $targetCalories): float
    {
        // Approximate calories per unit for common ingredients
        $caloriesPerUnit = [
            'Oats' => 3.89, // per gram
            'Banana' => 89, // per piece
            'Milk' => 0.42, // per ml
            'Honey' => 64, // per tbsp
            'Chicken Breast' => 1.65, // per gram
            'Rice' => 1.30, // per gram (cooked)
            'Broccoli' => 0.34, // per gram
            'Olive Oil' => 119, // per tbsp
            'Salmon' => 2.08, // per gram
            'Sweet Potato' => 0.86, // per gram
            'Spinach' => 0.23, // per gram
            'Avocado' => 1.60, // per gram
            'Apple' => 52, // per piece
            'Almonds' => 5.79 // per gram
        ];
        
        if (!isset($caloriesPerUnit[$ingredientName])) {
            return 0;
        }
        
        $quantity = $targetCalories / $caloriesPerUnit[$ingredientName];
        
        // Round to reasonable quantities
        if (in_array($ingredientName, ['Banana', 'Apple'])) {
            return max(0.5, round($quantity * 2) / 2); // Round to nearest 0.5 for pieces
        }
        
        return round($quantity, 1);
    }

    /**
     * Determine if a day should be a refeed day (higher calories)
     */
    public function isRefeedDay(Carbon $date, int $weekNumber): bool
    {
        // Refeed days typically occur once per week, usually on weekends
        // Frequency increases as diet progresses to prevent metabolic adaptation
        
        $dayOfWeek = $date->dayOfWeek;
        $isWeekend = in_array($dayOfWeek, [6, 0]); // Saturday or Sunday
        
        // Base refeed frequency
        $refeedFrequency = match(true) {
            $weekNumber <= 4 => 0.14, // Once per week (14% chance daily)
            $weekNumber <= 8 => 0.20, // 1.4 times per week
            default => 0.28 // 2 times per week for later weeks
        };
        
        // Higher chance on weekends
        if ($isWeekend) {
            $refeedFrequency *= 2;
        }
        
        return mt_rand() / mt_getrandmax() < $refeedFrequency;
    }

    /**
     * Calculate refeed day calories (maintenance or slight surplus)
     */
    public function calculateRefeedCalories(float $currentWeight): int
    {
        // Refeed days are typically at maintenance or slight surplus
        $maintenanceCalories = $this->calculateDailyCalories($currentWeight, 'maintenance');
        
        // Add 0-200 extra calories for refeed
        $extraCalories = mt_rand(0, 200);
        
        return $maintenanceCalories + $extraCalories;
    }

    /**
     * Generate nutrition data for a specific day
     */
    public function generateDayNutritionData(Carbon $date, float $currentWeight, int $weekNumber, $userId): array
    {
        // Determine if this is a refeed day
        $isRefeedDay = $this->isRefeedDay($date, $weekNumber);
        
        // Calculate target calories
        if ($isRefeedDay) {
            $targetCalories = $this->calculateRefeedCalories($currentWeight);
        } else {
            $targetCalories = $this->calculateDailyCalories($currentWeight, 'weight_loss');
        }
        
        // Generate meal plan
        $mealPlan = $this->generateMealPlan($targetCalories, $date);
        
        return [
            'date' => $date,
            'target_calories' => $targetCalories,
            'is_refeed_day' => $isRefeedDay,
            'meals' => $mealPlan,
            'user_id' => $userId
        ];
    }

    /**
     * Get fallback ingredients if specific ingredients don't exist
     */
    public function getFallbackIngredients(): array
    {
        return [
            // Basic macronutrient sources that should always be available
            'protein_sources' => [
                ['name' => 'Chicken Breast', 'protein' => 31, 'carbs' => 0, 'fats' => 3.6, 'unit' => 'g'],
                ['name' => 'Eggs', 'protein' => 13, 'carbs' => 1.1, 'fats' => 11, 'unit' => 'pc'],
                ['name' => 'Greek Yogurt', 'protein' => 10, 'carbs' => 4, 'fats' => 0, 'unit' => 'g']
            ],
            'carb_sources' => [
                ['name' => 'Rice', 'protein' => 2.7, 'carbs' => 28, 'fats' => 0.3, 'unit' => 'g'],
                ['name' => 'Oats', 'protein' => 17, 'carbs' => 66, 'fats' => 7, 'unit' => 'g'],
                ['name' => 'Banana', 'protein' => 1.1, 'carbs' => 23, 'fats' => 0.3, 'unit' => 'pc']
            ],
            'fat_sources' => [
                ['name' => 'Olive Oil', 'protein' => 0, 'carbs' => 0, 'fats' => 14, 'unit' => 'tbsp'],
                ['name' => 'Almonds', 'protein' => 21, 'carbs' => 22, 'fats' => 50, 'unit' => 'g'],
                ['name' => 'Avocado', 'protein' => 2, 'carbs' => 9, 'fats' => 15, 'unit' => 'g']
            ],
            'vegetables' => [
                ['name' => 'Broccoli', 'protein' => 3, 'carbs' => 7, 'fats' => 0.4, 'unit' => 'g'],
                ['name' => 'Spinach', 'protein' => 3, 'carbs' => 4, 'fats' => 0.4, 'unit' => 'g'],
                ['name' => 'Carrots', 'protein' => 0.9, 'carbs' => 10, 'fats' => 0.2, 'unit' => 'g']
            ]
        ];
    }

    /**
     * Validate and ensure ingredient exists, create fallback if needed
     */
    public function ensureIngredientExists(string $ingredientName, $userId): ?\App\Models\Ingredient
    {
        // Try to find existing ingredient
        $ingredient = \App\Models\Ingredient::where('name', $ingredientName)
            ->where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereNull('user_id'); // Global ingredients
            })
            ->first();
            
        if ($ingredient) {
            return $ingredient;
        }
        
        // Create fallback ingredient if it doesn't exist
        return $this->createFallbackIngredient($ingredientName, $userId);
    }

    /**
     * Create a fallback ingredient with reasonable nutritional values
     */
    private function createFallbackIngredient(string $ingredientName, $userId): ?\App\Models\Ingredient
    {
        $fallbacks = $this->getFallbackIngredients();
        
        // Search through fallback categories
        foreach ($fallbacks as $category => $ingredients) {
            foreach ($ingredients as $fallbackData) {
                if ($fallbackData['name'] === $ingredientName) {
                    // Get appropriate unit
                    $unit = \App\Models\Unit::where('abbreviation', $fallbackData['unit'])->first();
                    if (!$unit) {
                        $unit = \App\Models\Unit::where('abbreviation', 'g')->first(); // Default to grams
                    }
                    
                    return \App\Models\Ingredient::create([
                        'name' => $ingredientName,
                        'protein' => $fallbackData['protein'],
                        'carbs' => $fallbackData['carbs'],
                        'fats' => $fallbackData['fats'],
                        'base_quantity' => 100,
                        'base_unit_id' => $unit->id,
                        'user_id' => $userId,
                        'sodium' => 0,
                        'fiber' => 0,
                        'added_sugars' => 0,
                        'iron' => 0,
                        'potassium' => 0,
                        'calcium' => 0,
                        'caffeine' => 0,
                        'cost_per_unit' => 0
                    ]);
                }
            }
        }
        
        // If no specific fallback found, create a generic one
        $unit = \App\Models\Unit::where('abbreviation', 'g')->first();
        
        return \App\Models\Ingredient::create([
            'name' => $ingredientName,
            'protein' => 5,
            'carbs' => 10,
            'fats' => 2,
            'base_quantity' => 100,
            'base_unit_id' => $unit->id,
            'user_id' => $userId,
            'sodium' => 0,
            'fiber' => 0,
            'added_sugars' => 0,
            'iron' => 0,
            'potassium' => 0,
            'calcium' => 0,
            'caffeine' => 0,
            'cost_per_unit' => 0
        ]);
    }

    /**
     * Get workout type based on day of week
     */
    private function getWorkoutType(int $dayOfWeek): string
    {
        return match($dayOfWeek) {
            1 => 'upper_body', // Monday
            2 => 'lower_body', // Tuesday  
            4 => 'push',       // Thursday
            5 => 'pull',       // Friday
            default => 'full_body'
        };
    }

    /**
     * Get typical meal times
     */
    private function getMealTime(string $meal, Carbon $date): Carbon
    {
        $mealTime = $date->copy();
        
        return match($meal) {
            'breakfast' => $mealTime->setTime(7, 30),
            'lunch' => $mealTime->setTime(12, 30),
            'dinner' => $mealTime->setTime(18, 30),
            'snacks' => $mealTime->setTime(15, 0),
            default => $mealTime->setTime(12, 0)
        };
    }

    /**
     * Generate structured workout programs that progress over 12 weeks
     */
    public function generateWorkoutPrograms(Carbon $startDate, int $weeks, string $programType, $userId): array
    {
        $programs = [];
        $exercises = $this->getExercisesForProgramType($programType, $userId);
        
        // Define workout split based on program type
        $workoutSplit = $this->getWorkoutSplit($programType);
        
        for ($week = 0; $week < $weeks; $week++) {
            $weekStart = $startDate->copy()->addWeeks($week)->startOfWeek();
            
            foreach ($workoutSplit as $dayOffset => $workoutData) {
                $workoutDate = $weekStart->copy()->addDays($dayOffset);
                
                foreach ($workoutData['exercises'] as $exerciseType) {
                    if (isset($exercises[$exerciseType])) {
                        $exercise = $exercises[$exerciseType];
                        
                        // Calculate sets and reps based on week and program type
                        $setsReps = $this->calculateSetsRepsForWeek($week + 1, $programType, $exerciseType);
                        
                        $programs[] = [
                            'user_id' => $userId,
                            'exercise_id' => $exercise->id,
                            'date' => $workoutDate,
                            'sets' => $setsReps['sets'],
                            'reps' => $setsReps['reps'],
                            'comments' => $this->generateProgramComments($week + 1, $exerciseType),
                            'priority' => $this->getExercisePriority($exerciseType),
                            'workout_type' => $workoutData['type']
                        ];
                    }
                }
            }
        }
        
        return $programs;
    }

    /**
     * Generate lift logs that follow program structure with progressive overload
     */
    public function generateLiftLogs(array $programs, Carbon $startDate, int $weeks, $userId): array
    {
        $liftLogs = [];
        $exerciseStartingWeights = $this->getStartingWeights($userId);
        
        foreach ($programs as $program) {
            $exercise = \App\Models\Exercise::find($program['exercise_id']);
            if (!$exercise) continue;
            
            $exerciseType = $this->getExerciseType($exercise->title);
            $weekNumber = $startDate->diffInWeeks($program['date']) + 1;
            
            // Calculate working weight based on progression
            $startingWeight = $exerciseStartingWeights[$exerciseType] ?? $this->getDefaultStartingWeight($exerciseType);
            $progressionWeights = $this->calculateStrengthProgression($startingWeight, $weeks, $exerciseType);
            $workingWeight = $progressionWeights[$weekNumber - 1] ?? $startingWeight;
            
            // Create lift log entry
            $liftLogData = [
                'user_id' => $userId,
                'exercise_id' => $program['exercise_id'],
                'logged_at' => $program['date']->copy()->setTime(
                    $this->getWorkoutTime($program['workout_type'])['hour'],
                    $this->getWorkoutTime($program['workout_type'])['minute']
                ),
                'comments' => $this->generateLiftLogComments($weekNumber, $exerciseType),
                'sets_data' => $this->generateLiftSetsData($program, $workingWeight, $exerciseType)
            ];
            
            $liftLogs[] = $liftLogData;
        }
        
        return $liftLogs;
    }

    /**
     * Generate lift sets data for a workout
     */
    private function generateLiftSetsData(array $program, float $workingWeight, string $exerciseType): array
    {
        $sets = [];
        $targetSets = $program['sets'];
        $targetReps = $program['reps'];
        
        // Generate warmup sets for compound movements
        if (in_array($exerciseType, ['squat', 'bench', 'deadlift', 'overhead_press'])) {
            $warmupSets = $this->generateWarmupSets($workingWeight, $exerciseType);
            $sets = array_merge($sets, $warmupSets);
        }
        
        // Generate working sets
        for ($set = 1; $set <= $targetSets; $set++) {
            $setWeight = $workingWeight;
            $setReps = $targetReps;
            
            // Add some variation to later sets (fatigue effect)
            if ($set > 1) {
                $fatigueReduction = ($set - 1) * 0.02; // 2% reduction per set
                $setReps = max(1, (int) round($targetReps * (1 - $fatigueReduction)));
            }
            
            $sets[] = [
                'weight' => $setWeight,
                'reps' => $setReps,
                'notes' => $set <= $targetSets ? 'Working set' : 'Drop set',
                'set_type' => 'working'
            ];
        }
        
        return $sets;
    }

    /**
     * Generate warmup sets for compound movements
     */
    private function generateWarmupSets(float $workingWeight, string $exerciseType): array
    {
        $warmupSets = [];
        
        // Bodyweight warmup for most exercises
        if ($workingWeight > 45) { // Only if working weight is above empty barbell
            $warmupSets[] = [
                'weight' => 45, // Empty barbell
                'reps' => 10,
                'notes' => 'Warmup - empty bar',
                'set_type' => 'warmup'
            ];
        }
        
        // Progressive warmup sets
        $warmupPercentages = [0.5, 0.7, 0.85];
        $warmupReps = [8, 5, 3];
        
        for ($i = 0; $i < count($warmupPercentages); $i++) {
            $warmupWeight = round($workingWeight * $warmupPercentages[$i] / 5) * 5; // Round to nearest 5
            if ($warmupWeight > 45) { // Only add if above empty bar
                $warmupSets[] = [
                    'weight' => $warmupWeight,
                    'reps' => $warmupReps[$i],
                    'notes' => 'Warmup - ' . ($warmupPercentages[$i] * 100) . '%',
                    'set_type' => 'warmup'
                ];
            }
        }
        
        return $warmupSets;
    }

    /**
     * Get exercises for specific program type, ensuring they exist
     */
    private function getExercisesForProgramType(string $programType, $userId): array
    {
        $exerciseTemplates = $this->getExerciseTemplates($programType);
        $exercises = [];
        
        foreach ($exerciseTemplates as $exerciseType => $exerciseData) {
            $exercise = $this->ensureExerciseExists($exerciseData['title'], $exerciseData['is_bodyweight'], $userId);
            if ($exercise) {
                $exercises[$exerciseType] = $exercise;
            }
        }
        
        return $exercises;
    }

    /**
     * Ensure exercise exists, create if needed
     */
    private function ensureExerciseExists(string $title, bool $isBodyweight, $userId): ?\App\Models\Exercise
    {
        // Try to find existing exercise
        $exercise = \App\Models\Exercise::where('title', $title)
            ->where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereNull('user_id'); // Global exercises
            })
            ->first();
            
        if ($exercise) {
            return $exercise;
        }
        
        // Create new exercise
        return \App\Models\Exercise::create([
            'title' => $title,
            'description' => "Generated exercise for transformation program",
            'is_bodyweight' => $isBodyweight,
            'user_id' => $userId
        ]);
    }

    /**
     * Get exercise templates for different program types
     */
    private function getExerciseTemplates(string $programType): array
    {
        $baseExercises = [
            'squat' => ['title' => 'Barbell Back Squat', 'is_bodyweight' => false],
            'bench' => ['title' => 'Barbell Bench Press', 'is_bodyweight' => false],
            'deadlift' => ['title' => 'Conventional Deadlift', 'is_bodyweight' => false],
            'overhead_press' => ['title' => 'Overhead Press', 'is_bodyweight' => false],
            'row' => ['title' => 'Barbell Row', 'is_bodyweight' => false],
            'pullup' => ['title' => 'Pull-ups', 'is_bodyweight' => true],
            'dip' => ['title' => 'Dips', 'is_bodyweight' => true],
            'lunge' => ['title' => 'Walking Lunges', 'is_bodyweight' => true],
            'plank' => ['title' => 'Plank', 'is_bodyweight' => true]
        ];
        
        return match($programType) {
            'strength' => [
                'squat' => $baseExercises['squat'],
                'bench' => $baseExercises['bench'],
                'deadlift' => $baseExercises['deadlift'],
                'overhead_press' => $baseExercises['overhead_press'],
                'row' => $baseExercises['row'],
                'pullup' => $baseExercises['pullup']
            ],
            'powerlifting' => [
                'squat' => $baseExercises['squat'],
                'bench' => $baseExercises['bench'],
                'deadlift' => $baseExercises['deadlift'],
                'row' => $baseExercises['row'],
                'overhead_press' => $baseExercises['overhead_press']
            ],
            'bodybuilding' => [
                'squat' => $baseExercises['squat'],
                'bench' => $baseExercises['bench'],
                'deadlift' => $baseExercises['deadlift'],
                'row' => $baseExercises['row'],
                'pullup' => $baseExercises['pullup'],
                'dip' => $baseExercises['dip'],
                'lunge' => $baseExercises['lunge'],
                'plank' => $baseExercises['plank']
            ],
            default => $baseExercises
        };
    }

    /**
     * Generate randomized measurement schedule with ~3 measurements per week
     * Varies days of the week and includes occasional gaps for realism
     */
    public function generateMeasurementSchedule(Carbon $startDate, int $weeks, int $avgPerWeek = 3): array
    {
        $schedule = [];
        $currentDate = $startDate->copy();
        
        for ($week = 0; $week < $weeks; $week++) {
            $weekStart = $currentDate->copy()->addWeeks($week)->startOfWeek();
            
            // Vary the number of measurements per week (2-4, averaging 3)
            $measurementsThisWeek = $this->getRandomMeasurementsPerWeek($avgPerWeek);
            
            // Generate random days for measurements, avoiding patterns
            $measurementDays = $this->getRandomMeasurementDays($measurementsThisWeek);
            
            foreach ($measurementDays as $dayOfWeek) {
                $measurementDate = $weekStart->copy()->addDays($dayOfWeek);
                
                $schedule[] = [
                    'date' => $measurementDate,
                    'week' => $week + 1,
                    'day_of_week' => $dayOfWeek,
                    'measure_weight' => true, // Weight measured most frequently
                    'measure_waist' => $this->shouldMeasureWaist($week, $dayOfWeek),
                    'measure_body_fat' => $this->shouldMeasureBodyFat($week, $dayOfWeek),
                    'measure_muscle_mass' => $this->shouldMeasureMuscleMass($week, $dayOfWeek),
                    'measure_additional' => $this->shouldMeasureAdditional($week, $dayOfWeek)
                ];
            }
        }
        
        return $schedule;
    }

    /**
     * Get random number of measurements per week, averaging the target
     */
    private function getRandomMeasurementsPerWeek(int $avgPerWeek): int
    {
        // Generate 2-4 measurements per week, weighted toward the average
        $options = [
            2 => 0.2, // 20% chance of 2 measurements
            3 => 0.5, // 50% chance of 3 measurements (target)
            4 => 0.3  // 30% chance of 4 measurements
        ];
        
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        foreach ($options as $count => $probability) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return $count;
            }
        }
        
        return $avgPerWeek; // Fallback
    }

    /**
     * Get random days of the week for measurements, avoiding regular patterns
     */
    private function getRandomMeasurementDays(int $count): array
    {
        $allDays = [0, 1, 2, 3, 4, 5, 6]; // Sunday through Saturday
        
        // Shuffle and take the required number of days
        shuffle($allDays);
        $selectedDays = array_slice($allDays, 0, $count);
        
        // Sort to maintain chronological order within the week
        sort($selectedDays);
        
        return $selectedDays;
    }

    /**
     * Determine if waist should be measured on this day
     */
    private function shouldMeasureWaist(int $week, int $dayOfWeek): bool
    {
        // Waist measured less frequently than weight (about 1-2 times per week)
        // More likely on weekends when people have more time
        $baseChance = 0.3; // 30% base chance
        
        // Higher chance on weekends
        if (in_array($dayOfWeek, [0, 6])) { // Sunday or Saturday
            $baseChance = 0.6;
        }
        
        // Slightly more frequent in later weeks as people get more consistent
        if ($week > 6) {
            $baseChance *= 1.2;
        }
        
        return mt_rand() / mt_getrandmax() < $baseChance;
    }

    /**
     * Determine if body fat should be measured on this day
     */
    private function shouldMeasureBodyFat(int $week, int $dayOfWeek): bool
    {
        // Body fat measured less frequently (about once per week)
        $baseChance = 0.15; // 15% base chance
        
        // More likely on specific days (e.g., Monday for weekly check-ins)
        if ($dayOfWeek === 1) { // Monday
            $baseChance = 0.4;
        }
        
        // More frequent in middle weeks when people are most engaged
        if ($week >= 4 && $week <= 8) {
            $baseChance *= 1.3;
        }
        
        return mt_rand() / mt_getrandmax() < $baseChance;
    }

    /**
     * Determine if muscle mass should be measured on this day
     */
    private function shouldMeasureMuscleMass(int $week, int $dayOfWeek): bool
    {
        // Muscle mass measured infrequently (about every 2 weeks)
        $baseChance = 0.08; // 8% base chance
        
        // More likely on weekends when using more sophisticated scales
        if (in_array($dayOfWeek, [0, 6])) {
            $baseChance = 0.2;
        }
        
        // More frequent in later weeks as people track body composition changes
        if ($week > 8) {
            $baseChance *= 1.5;
        }
        
        return mt_rand() / mt_getrandmax() < $baseChance;
    }

    /**
     * Determine if additional measurements should be taken
     */
    private function shouldMeasureAdditional(int $week, int $dayOfWeek): bool
    {
        // Additional measurements (chest, arm, thigh) taken occasionally
        $baseChance = 0.05; // 5% base chance
        
        // More likely at the beginning and end of transformation
        if ($week <= 2 || $week >= 10) {
            $baseChance = 0.15;
        }
        
        // More likely on weekends
        if (in_array($dayOfWeek, [0, 6])) {
            $baseChance *= 2;
        }
        
        return mt_rand() / mt_getrandmax() < $baseChance;
    }

    /**
     * Calculate body fat percentage progression over time
     */
    public function calculateBodyFatProgression(float $startBodyFat, float $targetBodyFat, array $measurementDates): array
    {
        $progression = [];
        $totalDays = count($measurementDates);
        
        if ($totalDays === 0) {
            return $progression;
        }
        
        $totalReduction = $startBodyFat - $targetBodyFat;
        
        foreach ($measurementDates as $index => $date) {
            $progressRatio = $totalDays > 1 ? $index / ($totalDays - 1) : 0;
            
            // Body fat reduction is slower initially, then accelerates
            $reductionMultiplier = 0.1 + ($progressRatio * 0.9);
            $currentReduction = $totalReduction * $reductionMultiplier;
            
            $currentBodyFat = $startBodyFat - $currentReduction;
            $progression[$date->format('Y-m-d')] = round(max($currentBodyFat, $targetBodyFat), 1);
        }
        
        return $progression;
    }

    /**
     * Calculate muscle mass progression correlated with strength gains
     */
    public function calculateMuscleMassProgression(float $startMass, array $strengthData, array $measurementDates): array
    {
        $progression = [];
        $totalDays = count($measurementDates);
        
        if ($totalDays === 0) {
            return $progression;
        }
        
        // Muscle mass increases in first 6-8 weeks (newbie gains), then stabilizes during cut
        foreach ($measurementDates as $index => $date) {
            $progressRatio = $totalDays > 1 ? $index / ($totalDays - 1) : 0;
            $weekNumber = floor($progressRatio * 12) + 1; // Map to 12-week program
            
            if ($weekNumber <= 6) {
                // Newbie gains phase - slight muscle increase
                $gainMultiplier = $weekNumber / 6;
                $muscleGain = 2.0 * $gainMultiplier; // Up to 2 lbs muscle gain
                $currentMass = $startMass + $muscleGain;
            } elseif ($weekNumber <= 10) {
                // Maintenance phase - muscle preserved
                $currentMass = $startMass + 2.0;
            } else {
                // Late cut phase - slight muscle loss
                $lossMultiplier = ($weekNumber - 10) / 2;
                $muscleLoss = 1.0 * $lossMultiplier; // Up to 1 lb muscle loss
                $currentMass = $startMass + 2.0 - $muscleLoss;
            }
            
            $progression[$date->format('Y-m-d')] = round($currentMass, 1);
        }
        
        return $progression;
    }

    /**
     * Calculate additional body measurements (chest, arm, thigh circumferences)
     */
    public function calculateAdditionalMeasurements(float $weightLossRatio, array $measurementDates): array
    {
        $measurements = [
            'chest' => [],
            'arm' => [],
            'thigh' => []
        ];
        
        if (empty($measurementDates)) {
            return $measurements;
        }
        
        // Starting measurements (typical for a 180lb male)
        $startingMeasurements = [
            'chest' => 42.0, // inches
            'arm' => 14.5,   // inches
            'thigh' => 24.0  // inches
        ];
        
        // Different reduction rates for different body parts
        $reductionRates = [
            'chest' => 0.4, // Chest reduces less than waist
            'arm' => 0.3,   // Arms reduce even less
            'thigh' => 0.5  // Thighs reduce moderately
        ];
        
        foreach ($measurementDates as $index => $date) {
            $progressRatio = count($measurementDates) > 1 ? $index / (count($measurementDates) - 1) : 0;
            
            foreach ($startingMeasurements as $bodyPart => $startValue) {
                $reductionRate = $reductionRates[$bodyPart];
                $totalReduction = $startValue * $weightLossRatio * $reductionRate;
                
                // Apply reduction with some lag (body measurements change slower)
                $laggedProgress = max(0, $progressRatio - 0.2); // 20% lag
                $currentReduction = $totalReduction * $laggedProgress;
                
                $currentMeasurement = $startValue - $currentReduction;
                $measurements[$bodyPart][$date->format('Y-m-d')] = round($currentMeasurement, 1);
            }
        }
        
        return $measurements;
    }

    /**
     * Generate body measurement data with progressive weight loss and waist reduction
     */
    public function generateBodyMeasurementData(Carbon $startDate, int $days, float $startWeight, float $targetWeight, float $startWaist, $userId): array
    {
        $bodyLogs = [];
        $weeks = (int) ceil($days / 7);
        
        // Calculate weight loss progression
        $weightProgression = $this->calculateWeightLossProgression($startWeight, $targetWeight, $days);
        
        // Calculate waist progression correlated with weight loss
        $weightLossRatio = ($startWeight - $targetWeight) / $startWeight;
        $waistProgression = $this->calculateWaistProgression($startWaist, $weightLossRatio, $days);
        
        // Generate measurement schedule
        $measurementSchedule = $this->generateMeasurementSchedule($startDate, $weeks);
        
        // Extract measurement dates for progression calculations
        $measurementDates = collect($measurementSchedule)->pluck('date')->toArray();
        
        // Calculate body fat progression (18% to 12%)
        $bodyFatProgression = $this->calculateBodyFatProgression(18.0, 12.0, $measurementDates);
        
        // Calculate muscle mass progression (starting at 150 lbs lean mass)
        $muscleMassProgression = $this->calculateMuscleMassProgression(150.0, [], $measurementDates);
        
        // Calculate additional measurements
        $additionalMeasurements = $this->calculateAdditionalMeasurements($weightLossRatio, $measurementDates);
        
        // Ensure measurement types exist
        $measurementTypes = [
            'weight' => $this->ensureMeasurementTypeExists('Weight', 'lbs', $userId),
            'waist' => $this->ensureMeasurementTypeExists('Waist', 'inches', $userId),
            'body_fat' => $this->ensureMeasurementTypeExists('Body Fat %', '%', $userId),
            'muscle_mass' => $this->ensureMeasurementTypeExists('Muscle Mass', 'lbs', $userId),
            'chest' => $this->ensureMeasurementTypeExists('Chest', 'inches', $userId),
            'arm' => $this->ensureMeasurementTypeExists('Arm', 'inches', $userId),
            'thigh' => $this->ensureMeasurementTypeExists('Thigh', 'inches', $userId),
        ];
        
        foreach ($measurementSchedule as $measurementDay) {
            $dayIndex = $startDate->diffInDays($measurementDay['date']);
            $dateKey = $measurementDay['date']->format('Y-m-d');
            
            // Weight measurements (most frequent)
            if ($measurementDay['measure_weight']) {
                $baseWeight = $weightProgression[$dayIndex] ?? $targetWeight;
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['weight']->id,
                    'value' => $baseWeight,
                    'logged_at' => $measurementDay['date']->copy()->setTime(7, 0), // Morning weigh-in
                    'comments' => $this->generateWeightMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Waist measurements (less frequent)
            if ($measurementDay['measure_waist']) {
                $baseWaist = $waistProgression[$dayIndex] ?? ($startWaist - ($startWaist * $weightLossRatio * 0.7));
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['waist']->id,
                    'value' => $baseWaist,
                    'logged_at' => $measurementDay['date']->copy()->setTime(7, 15), // After weight measurement
                    'comments' => $this->generateWaistMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Body fat measurements
            if ($measurementDay['measure_body_fat'] && isset($bodyFatProgression[$dateKey])) {
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['body_fat']->id,
                    'value' => $bodyFatProgression[$dateKey],
                    'logged_at' => $measurementDay['date']->copy()->setTime(7, 20),
                    'comments' => $this->generateBodyFatMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Muscle mass measurements
            if ($measurementDay['measure_muscle_mass'] && isset($muscleMassProgression[$dateKey])) {
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['muscle_mass']->id,
                    'value' => $muscleMassProgression[$dateKey],
                    'logged_at' => $measurementDay['date']->copy()->setTime(7, 25),
                    'comments' => $this->generateMuscleMassComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Additional measurements (chest, arm, thigh)
            if ($measurementDay['measure_additional']) {
                foreach (['chest', 'arm', 'thigh'] as $bodyPart) {
                    if (isset($additionalMeasurements[$bodyPart][$dateKey])) {
                        $bodyLogs[] = [
                            'user_id' => $userId,
                            'measurement_type_id' => $measurementTypes[$bodyPart]->id,
                            'value' => $additionalMeasurements[$bodyPart][$dateKey],
                            'logged_at' => $measurementDay['date']->copy()->setTime(7, 30 + array_search($bodyPart, ['chest', 'arm', 'thigh']) * 2),
                            'comments' => $this->generateAdditionalMeasurementComments($bodyPart, $measurementDay['date'])
                        ];
                    }
                }
            }
        }
        
        return $bodyLogs;
    }

    /**
     * Generate comprehensive measurements coordinating all measurement types
     * Ensures correlations between related metrics and realistic timing
     */
    public function generateComprehensiveMeasurements(Carbon $startDate, int $weeks, array $transformationParams, $userId): array
    {
        $bodyLogs = [];
        
        // Extract transformation parameters
        $startWeight = $transformationParams['start_weight'] ?? 180.0;
        $targetWeight = $transformationParams['target_weight'] ?? 165.0;
        $startWaist = $transformationParams['start_waist'] ?? 36.0;
        $startBodyFat = $transformationParams['start_body_fat'] ?? 18.0;
        $targetBodyFat = $transformationParams['target_body_fat'] ?? 12.0;
        $startMuscleMass = $transformationParams['start_muscle_mass'] ?? 150.0;
        
        $days = $weeks * 7;
        $weightLossRatio = ($startWeight - $targetWeight) / $startWeight;
        
        // Generate measurement schedule
        $measurementSchedule = $this->generateMeasurementSchedule($startDate, $weeks);
        
        // Apply measurement gaps for realism
        $variationService = new RealisticVariationService();
        $measurementSchedule = $variationService->addMeasurementGaps($measurementSchedule, 0.12);
        
        // Extract measurement dates for progression calculations
        $measurementDates = collect($measurementSchedule)->pluck('date')->toArray();
        
        if (empty($measurementDates)) {
            return $bodyLogs;
        }
        
        // Calculate all progressions
        $weightProgression = $this->calculateWeightLossProgression($startWeight, $targetWeight, $days);
        $waistProgression = $this->calculateWaistProgression($startWaist, $weightLossRatio, $days);
        $bodyFatProgression = $this->calculateBodyFatProgression($startBodyFat, $targetBodyFat, $measurementDates);
        $muscleMassProgression = $this->calculateMuscleMassProgression($startMuscleMass, [], $measurementDates);
        $additionalMeasurements = $this->calculateAdditionalMeasurements($weightLossRatio, $measurementDates);
        
        // Apply realistic variations to progressions
        $weightProgression = $variationService->simulateWhooshEffect($weightProgression, 0.15);
        $weightProgression = $variationService->addPlateauPeriods($weightProgression, 7);
        
        // Ensure measurement types exist
        $measurementTypes = $this->ensureAllMeasurementTypesExist($userId);
        
        foreach ($measurementSchedule as $measurementDay) {
            $dayIndex = $startDate->diffInDays($measurementDay['date']);
            $dateKey = $measurementDay['date']->format('Y-m-d');
            
            // Weight measurements (most frequent)
            if ($measurementDay['measure_weight']) {
                $baseWeight = $weightProgression[$dayIndex] ?? $targetWeight;
                $variatedWeight = $variationService->addWeightFluctuations($baseWeight, $measurementDay['date']);
                $preciseWeight = $variationService->addMeasurementPrecisionVariation($variatedWeight, 'weight');
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['weight']->id,
                    'value' => $preciseWeight,
                    'logged_at' => $this->getRealisticMeasurementTime($measurementDay['date'], 'weight'),
                    'comments' => $this->generateWeightMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Waist measurements (correlated with weight loss)
            if ($measurementDay['measure_waist']) {
                $baseWaist = $waistProgression[$dayIndex] ?? ($startWaist - ($startWaist * $weightLossRatio * 0.7));
                $variatedWaist = $variationService->addWaistVariations($baseWaist, $measurementDay['date']);
                $preciseWaist = $variationService->addMeasurementPrecisionVariation($variatedWaist, 'waist');
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['waist']->id,
                    'value' => $preciseWaist,
                    'logged_at' => $this->getRealisticMeasurementTime($measurementDay['date'], 'waist'),
                    'comments' => $this->generateWaistMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Body fat measurements
            if ($measurementDay['measure_body_fat'] && isset($bodyFatProgression[$dateKey])) {
                $baseBodyFat = $bodyFatProgression[$dateKey];
                $variatedBodyFat = $variationService->addBodyFatVariation($baseBodyFat, 4.0);
                $preciseBodyFat = $variationService->addMeasurementPrecisionVariation($variatedBodyFat, 'body_fat');
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['body_fat']->id,
                    'value' => $preciseBodyFat,
                    'logged_at' => $this->getRealisticMeasurementTime($measurementDay['date'], 'body_fat'),
                    'comments' => $this->generateBodyFatMeasurementComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Muscle mass measurements
            if ($measurementDay['measure_muscle_mass'] && isset($muscleMassProgression[$dateKey])) {
                $baseMuscleMass = $muscleMassProgression[$dateKey];
                $variatedMuscleMass = $variationService->addMuscleMassVariation($baseMuscleMass, 3.0);
                $preciseMuscleMass = $variationService->addMeasurementPrecisionVariation($variatedMuscleMass, 'muscle_mass');
                
                $bodyLogs[] = [
                    'user_id' => $userId,
                    'measurement_type_id' => $measurementTypes['muscle_mass']->id,
                    'value' => $preciseMuscleMass,
                    'logged_at' => $this->getRealisticMeasurementTime($measurementDay['date'], 'muscle_mass'),
                    'comments' => $this->generateMuscleMassComments($measurementDay['date'], $dayIndex, $days)
                ];
            }
            
            // Additional measurements (chest, arm, thigh)
            if ($measurementDay['measure_additional']) {
                foreach (['chest', 'arm', 'thigh'] as $bodyPart) {
                    if (isset($additionalMeasurements[$bodyPart][$dateKey])) {
                        $baseValue = $additionalMeasurements[$bodyPart][$dateKey];
                        $preciseValue = $variationService->addMeasurementPrecisionVariation($baseValue, $bodyPart);
                        
                        $bodyLogs[] = [
                            'user_id' => $userId,
                            'measurement_type_id' => $measurementTypes[$bodyPart]->id,
                            'value' => $preciseValue,
                            'logged_at' => $this->getRealisticMeasurementTime($measurementDay['date'], $bodyPart),
                            'comments' => $this->generateAdditionalMeasurementComments($bodyPart, $measurementDay['date'])
                        ];
                    }
                }
            }
        }
        
        return $bodyLogs;
    }

    /**
     * Ensure all measurement types exist for comprehensive tracking
     */
    private function ensureAllMeasurementTypesExist($userId): array
    {
        $measurementTypes = [
            'weight' => ['name' => 'Weight', 'unit' => 'lbs'],
            'waist' => ['name' => 'Waist', 'unit' => 'inches'],
            'body_fat' => ['name' => 'Body Fat %', 'unit' => '%'],
            'muscle_mass' => ['name' => 'Muscle Mass', 'unit' => 'lbs'],
            'chest' => ['name' => 'Chest', 'unit' => 'inches'],
            'arm' => ['name' => 'Arm', 'unit' => 'inches'],
            'thigh' => ['name' => 'Thigh', 'unit' => 'inches'],
        ];
        
        $createdTypes = [];
        foreach ($measurementTypes as $key => $typeData) {
            $createdTypes[$key] = $this->ensureMeasurementTypeExists($typeData['name'], $typeData['unit'], $userId);
        }
        
        return $createdTypes;
    }

    /**
     * Get realistic measurement time based on measurement type and user patterns
     */
    private function getRealisticMeasurementTime(Carbon $date, string $measurementType): Carbon
    {
        $baseTime = match($measurementType) {
            'weight' => $date->copy()->setTime(7, 0), // Morning weigh-in
            'waist' => $date->copy()->setTime(7, 15), // After weight
            'body_fat' => $date->copy()->setTime(7, 20), // After waist
            'muscle_mass' => $date->copy()->setTime(7, 25), // After body fat
            'chest', 'arm', 'thigh' => $date->copy()->setTime(7, 30), // After main measurements
            default => $date->copy()->setTime(7, 0)
        };
        
        // Add realistic timing variations
        $variationService = new RealisticVariationService();
        return $variationService->addMeasurementTimingVariation($baseTime, $measurementType);
    }

    /**
     * Generate measurement summary and progress tracking data
     */
    public function generateMeasurementSummary(array $bodyLogs, Carbon $startDate, Carbon $endDate): array
    {
        $summary = [
            'total_measurements' => count($bodyLogs),
            'measurement_period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'duration_weeks' => $startDate->diffInWeeks($endDate)
            ],
            'measurement_types' => [],
            'progress_summary' => []
        ];
        
        // Group measurements by type
        $measurementsByType = [];
        foreach ($bodyLogs as $log) {
            $typeId = $log['measurement_type_id'];
            if (!isset($measurementsByType[$typeId])) {
                $measurementsByType[$typeId] = [];
            }
            $measurementsByType[$typeId][] = $log;
        }
        
        // Calculate summary for each measurement type
        foreach ($measurementsByType as $typeId => $measurements) {
            $values = array_column($measurements, 'value');
            $dates = array_column($measurements, 'logged_at');
            
            $firstValue = reset($values);
            $lastValue = end($values);
            $change = $lastValue - $firstValue;
            $changePercent = $firstValue != 0 ? ($change / $firstValue) * 100 : 0;
            
            $summary['measurement_types'][$typeId] = [
                'count' => count($measurements),
                'first_value' => $firstValue,
                'last_value' => $lastValue,
                'change' => round($change, 2),
                'change_percent' => round($changePercent, 2),
                'min_value' => min($values),
                'max_value' => max($values),
                'average_value' => round(array_sum($values) / count($values), 2),
                'first_date' => reset($dates),
                'last_date' => end($dates)
            ];
        }
        
        return $summary;
    }
    /**
 
    * Apply realistic variations to body measurement data
     */
    public function applyBodyMeasurementVariations(array $bodyLogs, RealisticVariationService $variationService): array
    {
        $modifiedLogs = [];
        
        foreach ($bodyLogs as $log) {
            $modifiedLog = $log;
            
            // Apply measurement variations based on measurement type
            if (isset($log['measurement_type_id'])) {
                $measurementType = \App\Models\MeasurementType::find($log['measurement_type_id']);
                
                if ($measurementType) {
                    switch (strtolower($measurementType->name)) {
                        case 'weight':
                            // Weight has more daily fluctuation (±1-3 lbs)
                            $modifiedLog['value'] = $variationService->addMeasurementVariation($log['value'], 1.5);
                            break;
                            
                        case 'waist':
                            // Waist measurements are more consistent (±0.25 inches)
                            $modifiedLog['value'] = $variationService->addMeasurementVariation($log['value'], 0.5);
                            break;
                            
                        default:
                            // Default variation for other measurements
                            $modifiedLog['value'] = $variationService->addMeasurementVariation($log['value'], 1.0);
                            break;
                    }
                }
            }
            
            // Add slight timing variations (±15 minutes)
            if (isset($log['logged_at'])) {
                $timeVariation = mt_rand(-15, 15);
                $modifiedLog['logged_at'] = $log['logged_at']->copy()->addMinutes($timeVariation);
            }
            
            $modifiedLogs[] = $modifiedLog;
        }
        
        return $modifiedLogs;
    }

    /**
     * Ensure measurement type exists, create if needed
     */
    private function ensureMeasurementTypeExists(string $name, string $defaultUnit, $userId): \App\Models\MeasurementType
    {
        // Try to find existing measurement type
        $measurementType = \App\Models\MeasurementType::where('name', $name)
            ->where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhereNull('user_id'); // Global measurement types
            })
            ->first();
            
        if ($measurementType) {
            return $measurementType;
        }
        
        // Create new measurement type
        return \App\Models\MeasurementType::create([
            'name' => $name,
            'default_unit' => $defaultUnit,
            'user_id' => $userId
        ]);
    }

    /**
     * Generate contextual comments for weight measurements
     */
    private function generateWeightMeasurementComments(Carbon $date, int $dayIndex, int $totalDays): string
    {
        $weekNumber = floor($dayIndex / 7) + 1;
        $progressPercent = ($dayIndex / $totalDays) * 100;
        
        $comments = [
            // Early weeks
            'Starting transformation journey',
            'Morning weigh-in',
            'Feeling motivated',
            'Tracking progress',
            
            // Mid transformation
            'Seeing some changes',
            'Staying consistent',
            'Progress is steady',
            'Feeling stronger',
            
            // Later weeks
            'Major progress visible',
            'Transformation showing',
            'Almost at goal',
            'Feeling amazing'
        ];
        
        // Select comment based on progress
        if ($progressPercent < 25) {
            $commentIndex = mt_rand(0, 3);
        } elseif ($progressPercent < 75) {
            $commentIndex = mt_rand(4, 7);
        } else {
            $commentIndex = mt_rand(8, 11);
        }
        
        return $comments[$commentIndex];
    }

    /**
     * Generate contextual comments for body fat measurements
     */
    private function generateBodyFatMeasurementComments(Carbon $date, int $dayIndex, int $totalDays): string
    {
        $progressPercent = ($dayIndex / $totalDays) * 100;
        
        $comments = [
            // Early measurements
            'Baseline body fat reading',
            'Starting body composition',
            'Weekly body fat check',
            
            // Mid transformation
            'Body fat decreasing',
            'Leaning out nicely',
            'Composition improving',
            'Getting more defined',
            
            // Later measurements
            'Significant fat loss',
            'Body fat goal reached',
            'Excellent definition',
            'Transformation complete'
        ];
        
        if ($progressPercent < 30) {
            $commentIndex = mt_rand(0, 2);
        } elseif ($progressPercent < 70) {
            $commentIndex = mt_rand(3, 6);
        } else {
            $commentIndex = mt_rand(7, 10);
        }
        
        return $comments[$commentIndex];
    }

    /**
     * Generate contextual comments for muscle mass measurements
     */
    private function generateMuscleMassComments(Carbon $date, int $dayIndex, int $totalDays): string
    {
        $progressPercent = ($dayIndex / $totalDays) * 100;
        
        $comments = [
            // Early measurements
            'Baseline muscle mass',
            'Starting lean mass reading',
            'Muscle mass tracking',
            
            // Mid transformation
            'Maintaining muscle well',
            'Good muscle retention',
            'Strength training paying off',
            'Preserving lean mass',
            
            // Later measurements
            'Muscle mass preserved',
            'Great muscle retention',
            'Lean and strong',
            'Optimal body composition'
        ];
        
        if ($progressPercent < 30) {
            $commentIndex = mt_rand(0, 2);
        } elseif ($progressPercent < 70) {
            $commentIndex = mt_rand(3, 6);
        } else {
            $commentIndex = mt_rand(7, 10);
        }
        
        return $comments[$commentIndex];
    }

    /**
     * Generate contextual comments for additional measurements
     */
    private function generateAdditionalMeasurementComments(string $bodyPart, Carbon $date): string
    {
        $comments = [
            'chest' => [
                'Chest measurement check',
                'Upper body progress',
                'Chest getting leaner',
                'Good chest definition'
            ],
            'arm' => [
                'Arm circumference check',
                'Arm measurement update',
                'Arms looking defined',
                'Good arm progress'
            ],
            'thigh' => [
                'Thigh measurement check',
                'Leg progress tracking',
                'Thighs getting leaner',
                'Lower body improvement'
            ]
        ];
        
        $bodyPartComments = $comments[$bodyPart] ?? ['Measurement update'];
        return $bodyPartComments[array_rand($bodyPartComments)];
    }

    /**
     * Generate contextual comments for waist measurements
     */
    private function generateWaistMeasurementComments(Carbon $date, int $dayIndex, int $totalDays): string
    {
        $progressPercent = ($dayIndex / $totalDays) * 100;
        
        $comments = [
            // Early measurements
            'Baseline measurement',
            'Starting point recorded',
            'Weekly check-in',
            
            // Mid transformation
            'Clothes fitting better',
            'Noticing changes',
            'Belt getting looser',
            'Progress visible',
            
            // Later measurements
            'Significant reduction',
            'Major improvement',
            'Goal almost reached',
            'Transformation complete'
        ];
        
        // Select comment based on progress
        if ($progressPercent < 30) {
            $commentIndex = mt_rand(0, 2);
        } elseif ($progressPercent < 70) {
            $commentIndex = mt_rand(3, 6);
        } else {
            $commentIndex = mt_rand(7, 10);
        }
        
        return $comments[$commentIndex];
    }

    /**
     * Get workout split based on program type
     */
    private function getWorkoutSplit(string $programType): array
    {
        return match($programType) {
            'strength' => [
                0 => ['type' => 'squat_bench', 'exercises' => ['squat', 'bench', 'row']], // Monday
                2 => ['type' => 'deadlift_press', 'exercises' => ['deadlift', 'overhead_press', 'pullup']], // Wednesday
                4 => ['type' => 'squat_bench', 'exercises' => ['squat', 'bench', 'row']], // Friday
            ],
            'powerlifting' => [
                0 => ['type' => 'squat_day', 'exercises' => ['squat', 'bench', 'row']], // Monday
                2 => ['type' => 'bench_day', 'exercises' => ['bench', 'overhead_press', 'row']], // Wednesday
                4 => ['type' => 'deadlift_day', 'exercises' => ['deadlift', 'squat']], // Friday
            ],
            'bodybuilding' => [
                0 => ['type' => 'push', 'exercises' => ['bench', 'overhead_press', 'dip']], // Monday
                1 => ['type' => 'pull', 'exercises' => ['deadlift', 'row', 'pullup']], // Tuesday
                3 => ['type' => 'legs', 'exercises' => ['squat', 'lunge']], // Thursday
                5 => ['type' => 'upper', 'exercises' => ['bench', 'row', 'pullup']], // Saturday
            ],
            default => [
                0 => ['type' => 'full_body', 'exercises' => ['squat', 'bench', 'row']], // Monday
                2 => ['type' => 'full_body', 'exercises' => ['deadlift', 'overhead_press', 'pullup']], // Wednesday
                4 => ['type' => 'full_body', 'exercises' => ['squat', 'bench', 'row']], // Friday
            ]
        };
    }

    /**
     * Calculate sets and reps for a given week and program type
     */
    private function calculateSetsRepsForWeek(int $week, string $programType, string $exerciseType): array
    {
        $baseSetRep = $this->getBaseSetRep($programType, $exerciseType);
        
        // Periodization - adjust sets/reps based on week
        $phase = match(true) {
            $week <= 4 => 'foundation',
            $week <= 8 => 'progression', 
            default => 'peak'
        };
        
        return match($phase) {
            'foundation' => [
                'sets' => $baseSetRep['sets'],
                'reps' => $baseSetRep['reps']
            ],
            'progression' => [
                'sets' => min(5, $baseSetRep['sets'] + 1),
                'reps' => max(3, $baseSetRep['reps'] - 1)
            ],
            'peak' => [
                'sets' => min(6, $baseSetRep['sets'] + 1),
                'reps' => max(1, $baseSetRep['reps'] - 2)
            ]
        };
    }

    /**
     * Get base sets and reps for exercise type and program
     */
    private function getBaseSetRep(string $programType, string $exerciseType): array
    {
        $defaults = [
            'strength' => [
                'squat' => ['sets' => 3, 'reps' => 5],
                'bench' => ['sets' => 3, 'reps' => 5],
                'deadlift' => ['sets' => 1, 'reps' => 5],
                'overhead_press' => ['sets' => 3, 'reps' => 5],
                'row' => ['sets' => 3, 'reps' => 5],
                'pullup' => ['sets' => 3, 'reps' => 8]
            ],
            'powerlifting' => [
                'squat' => ['sets' => 4, 'reps' => 3],
                'bench' => ['sets' => 4, 'reps' => 3],
                'deadlift' => ['sets' => 1, 'reps' => 3],
                'overhead_press' => ['sets' => 3, 'reps' => 5],
                'row' => ['sets' => 3, 'reps' => 5]
            ],
            'bodybuilding' => [
                'squat' => ['sets' => 4, 'reps' => 8],
                'bench' => ['sets' => 4, 'reps' => 8],
                'deadlift' => ['sets' => 3, 'reps' => 6],
                'overhead_press' => ['sets' => 3, 'reps' => 10],
                'row' => ['sets' => 4, 'reps' => 8],
                'pullup' => ['sets' => 3, 'reps' => 10],
                'dip' => ['sets' => 3, 'reps' => 12],
                'lunge' => ['sets' => 3, 'reps' => 12],
                'plank' => ['sets' => 3, 'reps' => 60] // seconds
            ]
        ];
        
        return $defaults[$programType][$exerciseType] ?? ['sets' => 3, 'reps' => 8];
    }

    /**
     * Get starting weights for different exercises
     */
    private function getStartingWeights($userId): array
    {
        // These could be customized based on user data in the future
        return [
            'squat' => 135,
            'bench' => 115,
            'deadlift' => 155,
            'overhead_press' => 75,
            'row' => 95,
            'pullup' => 0, // bodyweight
            'dip' => 0, // bodyweight
            'lunge' => 0, // bodyweight
            'plank' => 0 // bodyweight
        ];
    }

    /**
     * Get default starting weight for exercise type
     */
    private function getDefaultStartingWeight(string $exerciseType): float
    {
        return match($exerciseType) {
            'squat' => 135,
            'bench' => 115,
            'deadlift' => 155,
            'overhead_press' => 75,
            'row' => 95,
            default => 0
        };
    }

    /**
     * Get exercise type from exercise title
     */
    private function getExerciseType(string $title): string
    {
        $titleLower = strtolower($title);
        
        if (str_contains($titleLower, 'squat')) return 'squat';
        if (str_contains($titleLower, 'bench')) return 'bench';
        if (str_contains($titleLower, 'deadlift')) return 'deadlift';
        if (str_contains($titleLower, 'overhead') || str_contains($titleLower, 'press')) return 'overhead_press';
        if (str_contains($titleLower, 'row')) return 'row';
        if (str_contains($titleLower, 'pull')) return 'pullup';
        if (str_contains($titleLower, 'dip')) return 'dip';
        if (str_contains($titleLower, 'lunge')) return 'lunge';
        if (str_contains($titleLower, 'plank')) return 'plank';
        
        return 'accessory';
    }

    /**
     * Get workout time based on workout type
     */
    private function getWorkoutTime(string $workoutType): array
    {
        return match($workoutType) {
            'morning' => ['hour' => 7, 'minute' => 0],
            'afternoon' => ['hour' => 15, 'minute' => 30],
            'evening' => ['hour' => 18, 'minute' => 0],
            default => ['hour' => 18, 'minute' => 0] // Default to evening
        };
    }

    /**
     * Generate program comments based on week and exercise
     */
    private function generateProgramComments(int $week, string $exerciseType): string
    {
        $phase = match(true) {
            $week <= 4 => 'Foundation Phase',
            $week <= 8 => 'Progression Phase',
            default => 'Peak Phase'
        };
        
        return "{$phase} - Week {$week}";
    }

    /**
     * Generate lift log comments
     */
    private function generateLiftLogComments(int $week, string $exerciseType): string
    {
        $comments = [
            "Week {$week} - Feeling strong",
            "Week {$week} - Good session",
            "Week {$week} - Progressive overload",
            "Week {$week} - Solid workout",
            "Week {$week} - Building strength"
        ];
        
        return $comments[array_rand($comments)];
    }

    /**
     * Get exercise priority for program ordering
     */
    private function getExercisePriority(string $exerciseType): int
    {
        return match($exerciseType) {
            'squat' => 1,
            'bench' => 2,
            'deadlift' => 3,
            'overhead_press' => 4,
            'row' => 5,
            'pullup' => 6,
            'dip' => 7,
            'lunge' => 8,
            'plank' => 9,
            default => 10
        };
    }

    /**
     * Apply realistic variations to lift logs including missed workouts
     */
    public function applyLiftLogVariations(array $liftLogs, float $missedWorkoutRate = 0.05): array
    {
        $variationService = new RealisticVariationService();
        $modifiedLiftLogs = [];
        
        foreach ($liftLogs as $liftLog) {
            // Determine if workout is missed
            $isMissed = mt_rand() / mt_getrandmax() < $missedWorkoutRate;
            
            if ($isMissed) {
                // Skip this workout (don't add to array)
                continue;
            }
            
            // Apply performance variations to sets
            $modifiedSetsData = [];
            foreach ($liftLog['sets_data'] as $setData) {
                if ($setData['set_type'] === 'working') {
                    // Apply variation to working sets only
                    $modifiedSet = $variationService->addPerformanceVariation($setData, 3.0);
                    $modifiedSetsData[] = $modifiedSet;
                } else {
                    // Keep warmup sets as-is
                    $modifiedSetsData[] = $setData;
                }
            }
            
            $liftLog['sets_data'] = $modifiedSetsData;
            $modifiedLiftLogs[] = $liftLog;
        }
        
        return $modifiedLiftLogs;
    }
}