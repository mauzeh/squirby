<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Exercise;
use App\Models\Ingredient;
use App\Services\TransformationConfig;
use App\Services\TransformationDataGenerator;
use App\Services\UserSeederService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AthleteTransformationSeeder extends Seeder
{
    private TransformationDataGenerator $dataGenerator;
    private UserSeederService $userSeederService;
    private TransformationConfig $config;
    private User $user;

    public function __construct()
    {
        $this->dataGenerator = new TransformationDataGenerator();
        $this->userSeederService = new UserSeederService();
    }

    /**
     * Validate the transformation configuration.
     */
    private function validateConfiguration(): void
    {
        if ($this->config->durationWeeks < 1 || $this->config->durationWeeks > 52) {
            throw new \InvalidArgumentException('Duration must be between 1 and 52 weeks.');
        }

        if ($this->config->startingWeight <= 0 || $this->config->targetWeight <= 0) {
            throw new \InvalidArgumentException('Weights must be positive numbers.');
        }

        if ($this->config->startingWeight <= $this->config->targetWeight) {
            throw new \InvalidArgumentException('Starting weight must be greater than target weight for weight loss transformation.');
        }

        if ($this->config->startingWaist <= 0) {
            throw new \InvalidArgumentException('Starting waist measurement must be a positive number.');
        }

        $validPrograms = ['strength', 'powerlifting', 'bodybuilding'];
        if (!in_array($this->config->programType, $validPrograms)) {
            throw new \InvalidArgumentException('Program type must be one of: ' . implode(', ', $validPrograms));
        }

        if ($this->config->missedWorkoutRate < 0 || $this->config->missedWorkoutRate > 1) {
            throw new \InvalidArgumentException('Missed workout rate must be between 0.0 and 1.0.');
        }

        // Validate start date is not too far in the future
        $maxFutureDate = Carbon::now()->addYear();
        if ($this->config->startDate->gt($maxFutureDate)) {
            throw new \InvalidArgumentException('Start date cannot be more than 1 year in the future.');
        }

        // Validate user exists if specified
        if ($this->config->user && !$this->config->user->exists) {
            throw new \InvalidArgumentException('Specified user does not exist in the database.');
        }
    }

    /**
     * Run the database seeds with default configuration.
     */
    public function run(): void
    {
        $config = new TransformationConfig();
        $this->runWithConfig($config);
    }

    /**
     * Run the database seeds with custom configuration.
     */
    public function runWithConfig(TransformationConfig $config): void
    {
        try {
            $this->config = $config;
            
            // Validate configuration
            $this->validateConfiguration();
            
            echo "🏋️ Starting Athlete Transformation Seeder...\n";
            
            $this->user = $this->createOrSelectDemoUser();
            $this->setupBaseData();
            $this->generatePrograms();
            $this->generateLiftLogs();
            $this->generateNutritionLogs();
            $this->generateBodyLogs();
            $this->outputSummary();
            
            echo "✅ Athlete Transformation Seeder completed successfully!\n";
            
        } catch (\Exception $e) {
            echo "❌ Error during transformation seeding: " . $e->getMessage() . "\n";
            
            // Log the full error for debugging
            \Log::error('AthleteTransformationSeeder failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'config' => [
                    'user_id' => $this->config->user?->id,
                    'duration_weeks' => $this->config->durationWeeks,
                    'start_date' => $this->config->startDate->format('Y-m-d'),
                ]
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a new demo user or select existing user based on configuration.
     */
    private function createOrSelectDemoUser(): User
    {
        if ($this->config->user) {
            echo "📋 Using existing user: {$this->config->user->name}\n";
            return $this->config->user;
        }

        // Check if demo user already exists
        $existingUser = User::where('email', 'demo.athlete@example.com')->first();
        
        if ($existingUser) {
            echo "📋 Using existing demo user: {$existingUser->name}\n";
            return $existingUser;
        }

        // Create new demo user
        echo "👤 Creating new demo athlete user...\n";
        
        $user = User::create([
            'name' => 'Demo Athlete',
            'email' => 'demo.athlete@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Assign athlete role if it exists
        $athleteRole = Role::where('name', 'Athlete')->first();
        if ($athleteRole) {
            $user->roles()->attach($athleteRole);
        }

        // Seed user with basic data (measurement types, ingredients, sample meal)
        $this->userSeederService->seedNewUser($user);
        
        echo "✅ Demo athlete user created: {$user->email}\n";
        
        return $user;
    }

    /**
     * Set up base data (exercises and ingredients) needed for the transformation.
     */
    private function setupBaseData(): void
    {
        echo "🔧 Setting up base data...\n";
        
        try {
            $this->ensureRequiredExercises();
            echo "   ✓ Required exercises verified/created\n";
            
            $this->ensureRequiredIngredients();
            
            echo "✅ Base data setup complete\n";
            
        } catch (\Exception $e) {
            echo "❌ Failed to setup base data: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Ensure required exercises exist for the transformation program.
     */
    private function ensureRequiredExercises(): void
    {
        $requiredExercises = [
            // Compound movements
            'Squat',
            'Bench Press',
            'Deadlift',
            'Overhead Press',
            'Barbell Row',
            
            // Accessory movements
            'Pull-ups',
            'Dips',
            'Lunges',
            'Push-ups',
            'Plank',
            'Bicep Curls',
            'Tricep Extensions',
            'Lateral Raises',
            'Face Pulls',
            'Romanian Deadlift',
        ];

        foreach ($requiredExercises as $exerciseTitle) {
            // First check if global exercise exists
            $globalExercise = Exercise::where('title', $exerciseTitle)
                ->whereNull('user_id')
                ->first();
                
            if (!$globalExercise) {
                // Check if user-specific exercise exists
                $userExercise = Exercise::where('title', $exerciseTitle)
                    ->where('user_id', $this->user->id)
                    ->first();
                    
                if (!$userExercise) {
                    // Create user-specific exercise
                    Exercise::create([
                        'title' => $exerciseTitle,
                        'description' => "Exercise for transformation program",
                        'user_id' => $this->user->id,
                        'is_bodyweight' => in_array($exerciseTitle, ['Pull-ups', 'Dips', 'Push-ups', 'Plank']),
                    ]);
                }
            }
        }
    }

    /**
     * Ensure required ingredients exist for nutrition logging by copying from admin user.
     */
    private function ensureRequiredIngredients(): void
    {
        // Find the admin user who has the comprehensive ingredient database
        $adminUser = User::where('email', 'admin@example.com')->first();
        
        if (!$adminUser) {
            echo "   ⚠️  Admin user not found, creating basic ingredients only\n";
            $this->createBasicIngredients();
            return;
        }

        // Get a comprehensive list of ingredients for varied nutrition logging
        $desiredIngredients = [
            // Proteins
            'Chicken Breast (Raw)',
            'Chicken Thigh (Skinless, Boneless)',
            'Beef, Ground (90% Lean, 10% Fat)',
            'Egg (L) whole',
            'Egg (L) white only',
            'Greek Yogurt (0% Fat - Trader Joe\'s)',
            'Greek Yogurt (Whole Milk - Chobani)',
            'Cashews, Whole (Salted, 50% Less Sodium, Trader Joe\'s)',
            
            // Carbohydrates
            'Rice, Brown Jasmine (Dry - Trader Joe\'s)',
            'Bread, Organic 5 Seed Multigrain (Trader Joe\'s)',
            'Banana (medium, around 150g with skin)',
            'Apple (small, around 150g in total)',
            'Blueberries (fresh)',
            'Granola (Chocolate Coffee, Trader Joe\'s)',
            'Corn, Roasted Frozen (Trader Joe\'s)',
            
            // Vegetables
            'Broccoli (raw)',
            'Brussels sprouts (steamed, no oil)',
            'Bell Pepper (Fresh)',
            'Green Beans',
            'Cucumber',
            'Grape Tomato',
            
            // Fats
            'Avocado',
            'Butter (unsalted, Clover)',
            'Cheese, Parmesan',
            'Cheese, Feta Crumbled (Trader Joe\'s)',
            
            // Other
            'Black Beans (canned, rinsed)',
            'Honey',
            'Balsamic Glaze (Trader Joe\'s)',
            'Fruits, Frozen (Average, Trader Joe\'s)',
        ];

        $copiedCount = 0;
        foreach ($desiredIngredients as $ingredientName) {
            // Check if user already has this ingredient
            $existing = $this->user->ingredients()->where('name', $ingredientName)->first();
            
            if (!$existing) {
                // Find the ingredient in admin's collection
                $adminIngredient = $adminUser->ingredients()->where('name', $ingredientName)->first();
                
                if ($adminIngredient) {
                    // Copy the ingredient to this user
                    $this->user->ingredients()->create([
                        'name' => $adminIngredient->name,
                        'base_quantity' => $adminIngredient->base_quantity,
                        'protein' => $adminIngredient->protein,
                        'carbs' => $adminIngredient->carbs,
                        'added_sugars' => $adminIngredient->added_sugars,
                        'fats' => $adminIngredient->fats,
                        'sodium' => $adminIngredient->sodium,
                        'iron' => $adminIngredient->iron,
                        'potassium' => $adminIngredient->potassium,
                        'fiber' => $adminIngredient->fiber,
                        'calcium' => $adminIngredient->calcium,
                        'caffeine' => $adminIngredient->caffeine,
                        'base_unit_id' => $adminIngredient->base_unit_id,
                        'cost_per_unit' => $adminIngredient->cost_per_unit,
                    ]);
                    $copiedCount++;
                }
            }
        }
        
        echo "   ✓ Copied {$copiedCount} ingredients from admin user\n";
    }

    /**
     * Create basic ingredients when admin user is not available.
     */
    private function createBasicIngredients(): void
    {
        $basicIngredients = [
            [
                'name' => 'Chicken Breast (Raw)',
                'base_quantity' => 100,
                'protein' => 25,
                'carbs' => 0,
                'fats' => 3,
                'base_unit_abbreviation' => 'g',
            ],
            [
                'name' => 'Rice, Brown (Dry)',
                'base_quantity' => 100,
                'protein' => 8,
                'carbs' => 77,
                'fats' => 3,
                'base_unit_abbreviation' => 'g',
            ],
            [
                'name' => 'Broccoli (raw)',
                'base_quantity' => 100,
                'protein' => 3,
                'carbs' => 7,
                'fats' => 0,
                'base_unit_abbreviation' => 'g',
            ],
        ];

        foreach ($basicIngredients as $ingredientData) {
            $existing = $this->user->ingredients()
                ->where('name', $ingredientData['name'])
                ->first();
                
            if (!$existing) {
                $unit = \App\Models\Unit::where('abbreviation', $ingredientData['base_unit_abbreviation'])->first();
                
                if ($unit) {
                    $this->user->ingredients()->create([
                        'name' => $ingredientData['name'],
                        'base_quantity' => $ingredientData['base_quantity'],
                        'protein' => $ingredientData['protein'],
                        'carbs' => $ingredientData['carbs'],
                        'added_sugars' => 0,
                        'fats' => $ingredientData['fats'],
                        'sodium' => 0,
                        'iron' => 0,
                        'potassium' => 0,
                        'fiber' => 0,
                        'calcium' => 0,
                        'caffeine' => 0,
                        'base_unit_id' => $unit->id,
                        'cost_per_unit' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * Generate workout programs for the transformation period with 5-day training split.
     */
    private function generatePrograms(): void
    {
        echo "🏋️ Generating workout programs...\n";
        
        try {
            $startDate = $this->config->startDate;
            $programsCreated = 0;
            $weeksToGenerate = min(4, $this->config->durationWeeks);
            
            // Define 5-day training split
            $trainingSchedule = $this->getTrainingSchedule();
            
            for ($week = 0; $week < $weeksToGenerate; $week++) {
                echo "   Creating programs for Week " . ($week + 1) . "...\n";
                
                foreach ($trainingSchedule as $dayOffset => $dayInfo) {
                    $workoutDate = $startDate->copy()->addWeeks($week)->addDays($dayOffset);
                    
                    foreach ($dayInfo['exercises'] as $priority => $exerciseInfo) {
                        $exercise = Exercise::where('title', $exerciseInfo['name'])
                            ->where(function($query) {
                                $query->whereNull('user_id')
                                      ->orWhere('user_id', $this->user->id);
                            })
                            ->first();
                            
                        if ($exercise) {
                            $this->user->programs()->create([
                                'exercise_id' => $exercise->id,
                                'date' => $workoutDate,
                                'sets' => $exerciseInfo['sets'],
                                'reps' => $exerciseInfo['reps'],
                                'comments' => "Week " . ($week + 1) . " - " . $dayInfo['name'],
                                'priority' => $priority + 1,
                            ]);
                            $programsCreated++;
                        }
                    }
                    
                    echo "     ✓ {$dayInfo['name']} - " . count($dayInfo['exercises']) . " exercises\n";
                }
            }
            
            echo "✅ Generated {$programsCreated} program entries for {$weeksToGenerate} weeks\n";
            
        } catch (\Exception $e) {
            echo "❌ Failed to generate programs: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Get the 5-day training schedule with exercise splits.
     */
    private function getTrainingSchedule(): array
    {
        return [
            1 => [ // Monday - Push (Chest, Shoulders, Triceps)
                'name' => 'Push Day',
                'exercises' => [
                    ['name' => 'Bench Press', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Overhead Press', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Dips', 'sets' => 3, 'reps' => 12],
                    ['name' => 'Lateral Raises', 'sets' => 3, 'reps' => 15],
                    ['name' => 'Tricep Extensions', 'sets' => 3, 'reps' => 12],
                ]
            ],
            2 => [ // Tuesday - Pull (Back, Biceps)
                'name' => 'Pull Day',
                'exercises' => [
                    ['name' => 'Deadlift', 'sets' => 4, 'reps' => 6],
                    ['name' => 'Barbell Row', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Pull-ups', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Face Pulls', 'sets' => 3, 'reps' => 15],
                    ['name' => 'Bicep Curls', 'sets' => 3, 'reps' => 12],
                ]
            ],
            3 => [ // Wednesday - Legs (Quads, Glutes, Hamstrings)
                'name' => 'Leg Day',
                'exercises' => [
                    ['name' => 'Squat', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => 10],
                    ['name' => 'Lunges', 'sets' => 3, 'reps' => 12],
                    ['name' => 'Plank', 'sets' => 3, 'reps' => 60], // 60 second holds
                ]
            ],
            5 => [ // Friday - Push (Volume)
                'name' => 'Push Day (Volume)',
                'exercises' => [
                    ['name' => 'Bench Press', 'sets' => 3, 'reps' => 12],
                    ['name' => 'Overhead Press', 'sets' => 3, 'reps' => 12],
                    ['name' => 'Push-ups', 'sets' => 3, 'reps' => 15],
                    ['name' => 'Lateral Raises', 'sets' => 4, 'reps' => 15],
                    ['name' => 'Tricep Extensions', 'sets' => 3, 'reps' => 15],
                ]
            ],
            6 => [ // Saturday - Pull (Volume)
                'name' => 'Pull Day (Volume)',
                'exercises' => [
                    ['name' => 'Barbell Row', 'sets' => 4, 'reps' => 10],
                    ['name' => 'Pull-ups', 'sets' => 4, 'reps' => 8],
                    ['name' => 'Romanian Deadlift', 'sets' => 3, 'reps' => 12],
                    ['name' => 'Face Pulls', 'sets' => 4, 'reps' => 15],
                    ['name' => 'Bicep Curls', 'sets' => 4, 'reps' => 12],
                ]
            ]
        ];
    }

    /**
     * Generate lift logs following the 5-day program structure with progressive overload.
     */
    private function generateLiftLogs(): void
    {
        echo "💪 Generating lift logs...\n";
        
        try {
            $startDate = $this->config->startDate;
            $totalLiftLogs = 0;
            $totalSets = 0;
            $weeksToGenerate = min(4, $this->config->durationWeeks);
            
            // Get training schedule and base weights for exercises
            $trainingSchedule = $this->getTrainingSchedule();
            $baseWeights = $this->getBaseWeights();
            
            for ($week = 0; $week < $weeksToGenerate; $week++) {
                echo "   Processing Week " . ($week + 1) . "...\n";
                
                foreach ($trainingSchedule as $dayOffset => $dayInfo) {
                    $workoutDate = $startDate->copy()->addWeeks($week)->addDays($dayOffset);
                    echo "     {$dayInfo['name']} ({$workoutDate->format('Y-m-d')})...\n";
                    
                    foreach ($dayInfo['exercises'] as $exerciseInfo) {
                        $exercise = Exercise::where('title', $exerciseInfo['name'])
                            ->where(function($query) {
                                $query->whereNull('user_id')
                                      ->orWhere('user_id', $this->user->id);
                            })
                            ->first();
                            
                        if ($exercise) {
                            // Calculate progressive weight
                            $baseWeight = $baseWeights[$exerciseInfo['name']] ?? 135;
                            $currentWeight = $this->calculateProgressiveWeight($baseWeight, $week, $exerciseInfo['name']);
                            
                            // Create lift log
                            $liftLog = $this->user->liftLogs()->create([
                                'exercise_id' => $exercise->id,
                                'logged_at' => $workoutDate,
                                'weight' => $currentWeight,
                                'comments' => "Week " . ($week + 1) . " - " . $dayInfo['name'],
                            ]);
                            $totalLiftLogs++;
                            
                            // Add sets with realistic progression
                            for ($set = 1; $set <= $exerciseInfo['sets']; $set++) {
                                $setWeight = $this->calculateSetWeight($currentWeight, $set, $exerciseInfo['sets'], $exerciseInfo['name']);
                                $setReps = $this->calculateSetReps($exerciseInfo['reps'], $set, $exerciseInfo['sets'], $exerciseInfo['name']);
                                
                                $liftLog->liftSets()->create([
                                    'reps' => $setReps,
                                    'weight' => $setWeight,
                                ]);
                                $totalSets++;
                            }
                            
                            echo "       ✓ {$exerciseInfo['name']}: {$currentWeight} lbs x {$exerciseInfo['sets']} sets\n";
                        } else {
                            echo "       ⚠️  Exercise '{$exerciseInfo['name']}' not found, skipping\n";
                        }
                    }
                }
            }
            
            echo "✅ Generated {$totalLiftLogs} lift logs with {$totalSets} sets for {$weeksToGenerate} weeks\n";
            
        } catch (\Exception $e) {
            echo "❌ Failed to generate lift logs: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Get base weights for different exercises.
     */
    private function getBaseWeights(): array
    {
        return [
            // Compound movements - heavier
            'Squat' => 185,
            'Deadlift' => 225,
            'Bench Press' => 155,
            'Barbell Row' => 135,
            'Overhead Press' => 95,
            'Romanian Deadlift' => 155,
            
            // Bodyweight and accessory - lighter or bodyweight
            'Pull-ups' => 0, // Bodyweight
            'Push-ups' => 0, // Bodyweight
            'Dips' => 0, // Bodyweight
            'Plank' => 0, // Bodyweight
            'Lunges' => 95,
            'Bicep Curls' => 65,
            'Tricep Extensions' => 75,
            'Lateral Raises' => 25,
            'Face Pulls' => 45,
        ];
    }

    /**
     * Calculate progressive weight increase over weeks.
     */
    private function calculateProgressiveWeight(float $baseWeight, int $week, string $exerciseName): float
    {
        if ($baseWeight == 0) {
            return 0; // Bodyweight exercises
        }
        
        // Different progression rates for different exercise types
        $progressionRates = [
            'Squat' => 10, // 10 lbs per week
            'Deadlift' => 15, // 15 lbs per week
            'Bench Press' => 7.5, // 7.5 lbs per week
            'Barbell Row' => 7.5,
            'Overhead Press' => 5, // Slower progression for overhead
            'Romanian Deadlift' => 10,
            'Lunges' => 5,
            'Bicep Curls' => 2.5,
            'Tricep Extensions' => 2.5,
            'Lateral Raises' => 2.5,
            'Face Pulls' => 2.5,
        ];
        
        $weeklyIncrease = $progressionRates[$exerciseName] ?? 5;
        return $baseWeight + ($week * $weeklyIncrease);
    }

    /**
     * Calculate weight for individual sets (accounting for fatigue).
     */
    private function calculateSetWeight(float $baseWeight, int $setNumber, int $totalSets, string $exerciseName): float
    {
        if ($baseWeight == 0) {
            return 0; // Bodyweight exercises
        }
        
        // First set is at full weight, subsequent sets may drop slightly due to fatigue
        if ($setNumber == 1) {
            return $baseWeight;
        }
        
        // For high-intensity exercises, maintain weight
        $maintainWeightExercises = ['Squat', 'Deadlift', 'Bench Press'];
        if (in_array($exerciseName, $maintainWeightExercises)) {
            return $baseWeight;
        }
        
        // For accessory exercises, slight drop in later sets
        $fatigueReduction = ($setNumber - 1) * 0.025; // 2.5% reduction per set after first
        return round($baseWeight * (1 - $fatigueReduction), 1);
    }

    /**
     * Calculate reps for individual sets (accounting for fatigue and rep schemes).
     */
    private function calculateSetReps(int $targetReps, int $setNumber, int $totalSets, string $exerciseName): int
    {
        // For plank, return target reps (seconds)
        if ($exerciseName == 'Plank') {
            return $targetReps;
        }
        
        // For bodyweight exercises, reps may decrease with fatigue
        $bodyweightExercises = ['Pull-ups', 'Push-ups', 'Dips'];
        if (in_array($exerciseName, $bodyweightExercises)) {
            if ($setNumber <= 2) {
                return $targetReps;
            } else {
                return max(1, $targetReps - ($setNumber - 2)); // Decrease by 1 rep per set after 2nd
            }
        }
        
        // For weighted exercises, maintain target reps
        return $targetReps;
    }

    /**
     * Generate nutrition logs with appropriate caloric intake for weight loss goals.
     */
    private function generateNutritionLogs(): void
    {
        echo "🍽️ Generating nutrition logs...\n";
        
        try {
            $startDate = $this->config->startDate;
            $totalFoodLogs = 0;
            $daysToGenerate = min(28, $this->config->durationWeeks * 7); // Generate up to 4 weeks for now
            
            echo "   Generating food logs for {$daysToGenerate} days...\n";
            
            // Define meal templates for variety
            $mealTemplates = $this->getMealTemplates();
            
            for ($day = 0; $day < $daysToGenerate; $day++) {
                $logDate = $startDate->copy()->addDays($day);
                
                if ($day % 7 == 0) {
                    echo "   Processing Week " . (intval($day / 7) + 1) . "...\n";
                }
                
                // Generate breakfast (7:00 AM)
                $breakfastTemplate = $mealTemplates['breakfast'][array_rand($mealTemplates['breakfast'])];
                $totalFoodLogs += $this->createMealFromTemplate($breakfastTemplate, $logDate->copy()->setTime(7, 0), 'breakfast', $day + 1);
                
                // Generate lunch (12:00 PM)
                $lunchTemplate = $mealTemplates['lunch'][array_rand($mealTemplates['lunch'])];
                $totalFoodLogs += $this->createMealFromTemplate($lunchTemplate, $logDate->copy()->setTime(12, 0), 'lunch', $day + 1);
                
                // Generate dinner (6:00 PM)
                $dinnerTemplate = $mealTemplates['dinner'][array_rand($mealTemplates['dinner'])];
                $totalFoodLogs += $this->createMealFromTemplate($dinnerTemplate, $logDate->copy()->setTime(18, 0), 'dinner', $day + 1);
                
                // Generate snacks (3:00 PM and 9:00 PM) - not every day
                if ($day % 3 == 0) { // Every 3rd day
                    $snackTemplate = $mealTemplates['snacks'][array_rand($mealTemplates['snacks'])];
                    $totalFoodLogs += $this->createMealFromTemplate($snackTemplate, $logDate->copy()->setTime(15, 0), 'snack', $day + 1);
                }
                
                if ($day % 4 == 0) { // Every 4th day
                    $eveningSnackTemplate = $mealTemplates['snacks'][array_rand($mealTemplates['snacks'])];
                    $totalFoodLogs += $this->createMealFromTemplate($eveningSnackTemplate, $logDate->copy()->setTime(21, 0), 'evening snack', $day + 1);
                }
            }
            
            echo "✅ Generated {$totalFoodLogs} nutrition logs for {$daysToGenerate} days\n";
            
        } catch (\Exception $e) {
            echo "❌ Failed to generate nutrition logs: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Get meal templates with ingredient combinations for variety.
     */
    private function getMealTemplates(): array
    {
        return [
            'breakfast' => [
                [
                    ['name' => 'Egg (L) whole', 'quantity' => 2],
                    ['name' => 'Bread, Organic 5 Seed Multigrain (Trader Joe\'s)', 'quantity' => 1],
                    ['name' => 'Avocado', 'quantity' => 50],
                ],
                [
                    ['name' => 'Greek Yogurt (0% Fat - Trader Joe\'s)', 'quantity' => 170],
                    ['name' => 'Blueberries (fresh)', 'quantity' => 74],
                    ['name' => 'Granola (Chocolate Coffee, Trader Joe\'s)', 'quantity' => 30],
                ],
                [
                    ['name' => 'Egg (L) white only', 'quantity' => 3],
                    ['name' => 'Bell Pepper (Fresh)', 'quantity' => 80],
                    ['name' => 'Cheese, Feta Crumbled (Trader Joe\'s)', 'quantity' => 28],
                ],
                [
                    ['name' => 'Greek Yogurt (Whole Milk - Chobani)', 'quantity' => 170],
                    ['name' => 'Banana (medium, around 150g with skin)', 'quantity' => 1],
                    ['name' => 'Honey', 'quantity' => 15],
                ],
            ],
            'lunch' => [
                [
                    ['name' => 'Chicken Breast (Raw)', 'quantity' => 150],
                    ['name' => 'Rice, Brown Jasmine (Dry - Trader Joe\'s)', 'quantity' => 60],
                    ['name' => 'Broccoli (raw)', 'quantity' => 150],
                ],
                [
                    ['name' => 'Chicken Thigh (Skinless, Boneless)', 'quantity' => 120],
                    ['name' => 'Black Beans (canned, rinsed)', 'quantity' => 125],
                    ['name' => 'Bell Pepper (Fresh)', 'quantity' => 100],
                    ['name' => 'Avocado', 'quantity' => 50],
                ],
                [
                    ['name' => 'Beef, Ground (90% Lean, 10% Fat)', 'quantity' => 113],
                    ['name' => 'Brussels sprouts (steamed, no oil)', 'quantity' => 150],
                    ['name' => 'Grape Tomato', 'quantity' => 100],
                ],
                [
                    ['name' => 'Greek Yogurt (0% Fat - Trader Joe\'s)', 'quantity' => 170],
                    ['name' => 'Cucumber', 'quantity' => 150],
                    ['name' => 'Cheese, Parmesan', 'quantity' => 20],
                    ['name' => 'Apple (small, around 150g in total)', 'quantity' => 1],
                ],
            ],
            'dinner' => [
                [
                    ['name' => 'Chicken Breast (Raw)', 'quantity' => 180],
                    ['name' => 'Green Beans', 'quantity' => 200],
                    ['name' => 'Butter (unsalted, Clover)', 'quantity' => 10],
                ],
                [
                    ['name' => 'Chicken Thigh (Skinless, Boneless)', 'quantity' => 150],
                    ['name' => 'Broccoli (raw)', 'quantity' => 200],
                    ['name' => 'Cheese, Parmesan', 'quantity' => 15],
                ],
                [
                    ['name' => 'Beef, Ground (90% Lean, 10% Fat)', 'quantity' => 113],
                    ['name' => 'Bell Pepper (Fresh)', 'quantity' => 150],
                    ['name' => 'Grape Tomato', 'quantity' => 150],
                    ['name' => 'Cheese, Feta Crumbled (Trader Joe\'s)', 'quantity' => 28],
                ],
                [
                    ['name' => 'Egg (L) whole', 'quantity' => 3],
                    ['name' => 'Brussels sprouts (steamed, no oil)', 'quantity' => 200],
                    ['name' => 'Avocado', 'quantity' => 100],
                ],
            ],
            'snacks' => [
                [
                    ['name' => 'Apple (small, around 150g in total)', 'quantity' => 1],
                    ['name' => 'Cashews, Whole (Salted, 50% Less Sodium, Trader Joe\'s)', 'quantity' => 30],
                ],
                [
                    ['name' => 'Greek Yogurt (0% Fat - Trader Joe\'s)', 'quantity' => 85],
                    ['name' => 'Blueberries (fresh)', 'quantity' => 74],
                ],
                [
                    ['name' => 'Banana (medium, around 150g with skin)', 'quantity' => 1],
                ],
                [
                    ['name' => 'Cucumber', 'quantity' => 100],
                    ['name' => 'Cheese, Goat (Creamy, Fresh, Trader Joe\'s)', 'quantity' => 28],
                ],
                [
                    ['name' => 'Fruits, Frozen (Average, Trader Joe\'s)', 'quantity' => 140],
                ],
            ],
        ];
    }

    /**
     * Create food log entries from a meal template.
     */
    private function createMealFromTemplate(array $template, Carbon $logTime, string $mealType, int $day): int
    {
        $logsCreated = 0;
        
        foreach ($template as $item) {
            $ingredient = $this->user->ingredients()->where('name', $item['name'])->first();
            
            if ($ingredient) {
                $this->user->foodLogs()->create([
                    'ingredient_id' => $ingredient->id,
                    'unit_id' => $ingredient->base_unit_id,
                    'quantity' => $item['quantity'],
                    'logged_at' => $logTime,
                    'notes' => "Transformation diet - Day {$day} {$mealType}",
                ]);
                $logsCreated++;
            }
        }
        
        return $logsCreated;
    }

    /**
     * Generate body measurement progression showing weight loss and waist reduction.
     */
    private function generateBodyLogs(): void
    {
        echo "📏 Generating body measurements...\n";
        
        try {
            // This will be implemented using TransformationDataGenerator
            // For now, create sample body logs
            $startDate = $this->config->startDate;
            $weightType = $this->user->measurementTypes()->where('name', 'Bodyweight')->first();
            $waistType = $this->user->measurementTypes()->where('name', 'Waist')->first();
            $totalMeasurements = 0;
            
            $weeksToGenerate = min(4, $this->config->durationWeeks); // Generate up to 4 weeks for now
            
            if ($weightType) {
                echo "   Generating weight measurements...\n";
                for ($week = 0; $week < $weeksToGenerate; $week++) {
                    $measurementDate = $startDate->copy()->addWeeks($week);
                    
                    // Progressive weight loss
                    $weight = $this->config->startingWeight - ($week * 1.5); // 1.5 lbs per week
                    
                    $this->user->bodyLogs()->create([
                        'measurement_type_id' => $weightType->id,
                        'value' => $weight,
                        'logged_at' => $measurementDate->setTime(7, 0), // Morning weigh-ins
                        'comments' => "Week " . ($week + 1) . " weigh-in",
                    ]);
                    $totalMeasurements++;
                    
                    echo "     ✓ Week " . ($week + 1) . ": {$weight} lbs\n";
                }
            } else {
                echo "   ⚠️  Bodyweight measurement type not found, skipping weight logs\n";
            }
            
            if ($waistType) {
                echo "   Generating waist measurements...\n";
                for ($week = 0; $week < $weeksToGenerate; $week++) {
                    $measurementDate = $startDate->copy()->addWeeks($week);
                    
                    // Progressive waist reduction
                    $waist = $this->config->startingWaist - ($week * 0.5); // 0.5 inches per week
                    
                    $this->user->bodyLogs()->create([
                        'measurement_type_id' => $waistType->id,
                        'value' => $waist,
                        'logged_at' => $measurementDate->setTime(7, 30), // After weigh-in
                        'comments' => "Week " . ($week + 1) . " measurement",
                    ]);
                    $totalMeasurements++;
                    
                    echo "     ✓ Week " . ($week + 1) . ": {$waist} inches\n";
                }
            } else {
                echo "   ⚠️  Waist measurement type not found, skipping waist logs\n";
            }
            
            echo "✅ Generated {$totalMeasurements} body measurements for {$weeksToGenerate} weeks\n";
            
        } catch (\Exception $e) {
            echo "❌ Failed to generate body measurements: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Output a summary of the generated transformation data.
     */
    private function outputSummary(): void
    {
        echo "\n📊 TRANSFORMATION SUMMARY\n";
        echo "========================\n";
        echo "User: {$this->user->name} ({$this->user->email})\n";
        echo "Duration: {$this->config->durationWeeks} weeks\n";
        echo "Start Date: {$this->config->startDate->format('Y-m-d')}\n";
        echo "Starting Weight: {$this->config->startingWeight} lbs\n";
        echo "Target Weight: {$this->config->targetWeight} lbs\n";
        echo "Starting Waist: {$this->config->startingWaist} inches\n";
        echo "Program Type: {$this->config->programType}\n\n";
        
        // Count generated data
        $programCount = $this->user->programs()->count();
        $liftLogCount = $this->user->liftLogs()->count();
        $foodLogCount = $this->user->foodLogs()->count();
        $bodyLogCount = $this->user->bodyLogs()->count();
        
        echo "Generated Data:\n";
        echo "- Programs: {$programCount}\n";
        echo "- Lift Logs: {$liftLogCount}\n";
        echo "- Food Logs: {$foodLogCount}\n";
        echo "- Body Measurements: {$bodyLogCount}\n\n";
        
        echo "🎯 Transformation journey data has been successfully generated!\n";
        echo "You can now explore the user's progress through the application.\n\n";
    }
}