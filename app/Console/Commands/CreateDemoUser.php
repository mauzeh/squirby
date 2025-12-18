<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\BodyLog;
use App\Models\MeasurementType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CreateDemoUser extends Command
{
    protected $signature = 'demo:create-user 
                            {--fresh : Delete existing demo user and create fresh}
                            {--email= : Custom email for the demo user (default: demo@example.com)}
                            {--name= : Custom name for the demo user (default: Demo User)}';
    protected $description = 'Create a demo user with sample lift logs and body measurements';

    public function handle()
    {
        $fresh = $this->option('fresh');
        $email = $this->option('email') ?: 'demo@example.com';
        $name = $this->option('name') ?: 'Demo User';
        
        // Check if demo user already exists
        $existingUser = User::where('email', $email)->first();
        
        if ($existingUser) {
            if ($fresh) {
                $this->info("Deleting existing user: {$email}...");
                $existingUser->forceDelete();
            } else {
                $this->error("User with email {$email} already exists. Use --fresh flag to recreate.");
                return 1;
            }
        }

        $this->info("Creating demo user: {$name} ({$email})...");
        
        // Create demo user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('demo'),
            'show_global_exercises' => true,
            'show_extra_weight' => false,
            'prefill_suggested_values' => true,
        ]);

        $this->info("✅ Demo user created (ID: {$user->id})");

        // Assign Athlete role
        $athleteRole = \App\Models\Role::where('name', 'Athlete')->first();
        if ($athleteRole) {
            $user->roles()->attach($athleteRole->id);
            $this->info("  Assigned Athlete role");
        }

        // Create measurement types
        $this->info('Creating measurement types...');
        $weightType = MeasurementType::create([
            'name' => 'Bodyweight',
            'default_unit' => 'lbs',
            'user_id' => $user->id,
        ]);

        $waistType = MeasurementType::create([
            'name' => 'Waist Size',
            'default_unit' => 'in',
            'user_id' => $user->id,
        ]);

        // Create body measurements over the past 90 days
        $this->info('Creating body measurements...');
        $this->createBodyMeasurements($user, $weightType, $waistType);

        // Create exercises and lift logs
        $this->info('Creating exercises and lift logs...');
        $exerciseModels = $this->createLiftLogs($user);

        // Create workouts
        $this->info('Creating workouts...');
        $this->createWorkouts($user, $exerciseModels);

        $this->info('');
        $this->info('🎉 Demo user setup complete!');
        $this->info('');
        $this->info('Login credentials:');
        $this->info("  Email: {$email}");
        $this->info('  Password: demo');
        
        return 0;
    }

    private function createBodyMeasurements(User $user, MeasurementType $weightType, MeasurementType $waistType)
    {
        $startWeight = 185;
        $startWaist = 36;
        $days = 180; // 6 months
        $numDataPoints = 12;

        $currentWeight = $startWeight;
        $currentWaist = $startWaist;
        $currentDay = 0;

        for ($i = 0; $i < $numDataPoints; $i++) {
            $progress = $i / ($numDataPoints - 1);

            // Variable time intervals - sometimes regular, sometimes gaps
            if ($progress < 0.3) {
                // Early phase: consistent logging every 10-15 days
                $dayGap = rand(10, 15);
            } elseif ($progress < 0.5) {
                // Vacation/break: 20-30 day gap (missed logging)
                $dayGap = rand(20, 30);
            } elseif ($progress < 0.7) {
                // Back to regular: 8-12 days
                $dayGap = rand(8, 12);
            } else {
                // Recent: more frequent 5-10 days
                $dayGap = rand(5, 10);
            }

            $currentDay += $dayGap;
            $daysAgo = $days - $currentDay;
            $date = Carbon::now()->subDays($daysAgo);

            // Weight loss with high variability (water weight, diet fluctuations)
            if ($progress < 0.2) {
                // Initial phase: some loss with high variability
                $weightChange = -0.8 + (rand(-25, 20) / 10);
                $waistChange = -0.3 + (rand(-10, 8) / 10);
            } elseif ($progress < 0.5) {
                // Stagnation phase: lots of ups and downs
                $weightChange = -0.2 + (rand(-30, 30) / 10);
                $waistChange = -0.1 + (rand(-15, 15) / 10);
            } elseif ($progress < 0.7) {
                // Acceleration phase: more consistent loss
                $weightChange = -0.9 + (rand(-20, 15) / 10);
                $waistChange = -0.35 + (rand(-10, 8) / 10);
            } else {
                // Maintenance phase: high fluctuations around target
                $weightChange = -0.3 + (rand(-35, 25) / 10);
                $waistChange = -0.15 + (rand(-12, 10) / 10);
            }

            $currentWeight += $weightChange;
            $currentWaist += $waistChange;

            // Keep values in reasonable ranges
            $currentWeight = max(170, min(190, $currentWeight));
            $currentWaist = max(32, min(37, $currentWaist));

            BodyLog::create([
                'user_id' => $user->id,
                'measurement_type_id' => $weightType->id,
                'value' => round($currentWeight, 1),
                'logged_at' => $date,
                'comments' => null,
            ]);

            BodyLog::create([
                'user_id' => $user->id,
                'measurement_type_id' => $waistType->id,
                'value' => round($currentWaist, 1),
                'logged_at' => $date,
                'comments' => null,
            ]);
        }

        $this->info("  Created {$numDataPoints} data points over {$days} days");
    }

    private function createLiftLogs(User $user)
    {
        // Get or create common exercises with tracking state and max rep targets
        $exercises = [
            'Back Squat' => ['start_weight' => 135, 'base_progression' => 5, 'current_weight' => 135, 'max_rep_target' => 1],
            'Bench Press' => ['start_weight' => 135, 'base_progression' => 5, 'current_weight' => 135, 'max_rep_target' => 2],
            'Deadlift' => ['start_weight' => 185, 'base_progression' => 10, 'current_weight' => 185, 'max_rep_target' => 1],
            'Strict Press' => ['start_weight' => 75, 'base_progression' => 5, 'current_weight' => 75, 'max_rep_target' => 3],
            'Clean & Jerk' => ['start_weight' => 95, 'base_progression' => 5, 'current_weight' => 95, 'max_rep_target' => 1],
            'Snatch' => ['start_weight' => 65, 'base_progression' => 5, 'current_weight' => 65, 'max_rep_target' => 1],
            'Pull-ups' => ['start_weight' => 0, 'base_progression' => 0, 'type' => 'bodyweight', 'current_weight' => 0],
            'Push-ups' => ['start_weight' => 0, 'base_progression' => 0, 'type' => 'bodyweight', 'current_weight' => 0],
            'Rowing' => ['start_weight' => 0, 'base_progression' => 0, 'type' => 'cardio', 'current_weight' => 0],
            'Banded Pull-Down' => ['start_weight' => 0, 'base_progression' => 0, 'type' => 'banded_resistance', 'current_weight' => 0],
        ];

        $exerciseModels = [];
        
        foreach ($exercises as $title => $config) {
            $exercise = Exercise::where('title', $title)
                ->where(function($q) use ($user) {
                    $q->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->first();

            if (!$exercise) {
                $exercise = Exercise::create([
                    'title' => $title,
                    'description' => "Demo {$title}",
                    'user_id' => $user->id,
                    'exercise_type' => $config['type'] ?? 'regular',
                ]);
            }

            $exerciseModels[$title] = [
                'model' => $exercise,
                'config' => $config,
            ];
        }

        // Create lift logs - ensure at least 18 logs per barbell exercise
        $weeks = 24;
        $logsCreated = 0;
        $loggedExercisesPerDay = []; // Track which exercises were logged on each day
        
        // Create logs for each exercise separately to ensure minimum count
        foreach ($exercises as $exerciseName => $exerciseConfig) {
            $exerciseData = $exerciseModels[$exerciseName];
            $exercise = $exerciseData['model'];
            $config = $exerciseData['config'];
            
            // Determine how many logs to create for this exercise
            $exerciseType = $exercise->exercise_type;
            if ($exerciseType === 'regular') {
                // Barbell exercises: at least 18 logs
                $numLogs = rand(18, 22);
            } elseif ($exerciseType === 'bodyweight') {
                // Bodyweight: 12-15 logs
                $numLogs = rand(12, 15);
            } elseif ($exerciseType === 'cardio') {
                // Cardio: 8-12 logs
                $numLogs = rand(8, 12);
            } else {
                // Banded: 10-14 logs
                $numLogs = rand(10, 14);
            }
            
            $currentDay = 0;
            
            for ($i = 0; $i < $numLogs; $i++) {
                $progress = $i / $numLogs;
                
                // Variable workout frequency - simulate vacations and inconsistent periods
                if ($progress < 0.2) {
                    // Early phase: consistent 3-5 days between workouts
                    $dayGap = rand(3, 5);
                } elseif ($progress < 0.35) {
                    // Vacation/break: 7-14 days gap
                    $dayGap = rand(7, 14);
                } elseif ($progress < 0.5) {
                    // Getting back: 4-6 days
                    $dayGap = rand(4, 6);
                } elseif ($progress < 0.65) {
                    // Consistent period: 3-5 days
                    $dayGap = rand(3, 5);
                } elseif ($progress < 0.75) {
                    // Busy period: 5-8 days
                    $dayGap = rand(5, 8);
                } else {
                    // Recent: very consistent 3-4 days
                    $dayGap = rand(3, 4);
                }
                
                $currentDay += $dayGap;
                $daysAgo = ($weeks * 7) - $currentDay;
                
                // Ensure no logs for today - add at least 1 day
                $daysAgo = max(1, $daysAgo);
                
                $date = Carbon::now()->subDays($daysAgo);
                $dateKey = $date->format('Y-m-d');
                
                // Initialize tracking for this day if needed
                if (!isset($loggedExercisesPerDay[$dateKey])) {
                    $loggedExercisesPerDay[$dateKey] = [];
                }
                
                // Skip if this exercise was already logged today
                if (in_array($exerciseName, $loggedExercisesPerDay[$dateKey])) {
                    // Try next day
                    $daysAgo--;
                    $date = Carbon::now()->subDays($daysAgo);
                    $dateKey = $date->format('Y-m-d');
                    
                    if (!isset($loggedExercisesPerDay[$dateKey])) {
                        $loggedExercisesPerDay[$dateKey] = [];
                    }
                    
                    // If still duplicate, skip this log
                    if (in_array($exerciseName, $loggedExercisesPerDay[$dateKey])) {
                        continue;
                    }
                }
                
                // Mark this exercise as logged for this day
                $loggedExercisesPerDay[$dateKey][] = $exerciseName;
                $logsCreated++;
                
                // Dynamic progression with phases
                if ($progress < 0.25) {
                    // Beginner gains: rapid progression
                    $progressionMultiplier = 1.5;
                } elseif ($progress < 0.5) {
                    // Stagnation: minimal progression, sometimes regression
                    $progressionMultiplier = rand(0, 10) < 7 ? 0 : 0.5;
                    if (rand(0, 10) < 2) {
                        $progressionMultiplier = -0.5; // Occasional deload
                    }
                } elseif ($progress < 0.75) {
                    // Acceleration: renewed focus
                    $progressionMultiplier = 1.2;
                } else {
                    // Advanced: slower but steady gains
                    $progressionMultiplier = 0.6;
                }

                // Update weight with variation
                $weightChange = $config['base_progression'] * $progressionMultiplier;
                $config['current_weight'] += $weightChange;

                // Round to nearest 5 lbs
                $weight = round($config['current_weight'] / 5) * 5;
                $weight = max($config['start_weight'] - 10, $weight); // Don't go too low

                // Create lift log
                $liftLog = LiftLog::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exercise->id,
                    'logged_at' => $date,
                    'comments' => null,
                ]);
                
                if ($exerciseType === 'bodyweight') {
                // For bodyweight exercises: just log 5 sets with varying reps
                $numSets = 5;
                $baseReps = $exerciseName === 'Push-ups' ? 15 : 8;
                
                // Progressive improvement over time
                $repBonus = floor($progress * 5); // Gain up to 5 reps over the training period
                
                for ($set = 0; $set < $numSets; $set++) {
                    // Reps decrease slightly with each set (fatigue)
                    $reps = $baseReps + $repBonus - $set + rand(-1, 1);
                    
                    LiftSet::create([
                        'lift_log_id' => $liftLog->id,
                        'weight' => 0,
                        'reps' => max(3, $reps),
                        'notes' => null,
                        'band_color' => null,
                    ]);
                }
                } elseif ($exerciseType === 'cardio') {
                // For cardio (rowing): log distance in meters as reps, time as weight
                $baseDistance = 2000; // meters
                $distanceBonus = floor($progress * 500); // Improve distance over time
                $distance = $baseDistance + $distanceBonus + rand(-100, 100);
                
                // Round to nearest 250m
                $distance = round($distance / 250) * 250;
                
                // Time in seconds (stored as weight for cardio)
                $timeInSeconds = 480 + rand(-60, 60); // Around 8 minutes
                
                LiftSet::create([
                    'lift_log_id' => $liftLog->id,
                    'weight' => $timeInSeconds,
                    'reps' => $distance,
                    'notes' => null,
                    'band_color' => null,
                ]);
                } elseif ($exerciseType === 'banded_resistance') {
                // For banded exercises: log sets with band colors
                $numSets = rand(3, 5);
                $baseReps = 12;
                $bandColors = ['red', 'black', 'purple', 'green', 'blue'];
                $bandColor = $bandColors[array_rand($bandColors)];
                
                for ($set = 0; $set < $numSets; $set++) {
                    $reps = $baseReps + rand(-2, 3);
                    
                    LiftSet::create([
                        'lift_log_id' => $liftLog->id,
                        'weight' => 0,
                        'reps' => max(8, $reps),
                        'notes' => null,
                        'band_color' => $bandColor,
                    ]);
                }
                } else {
                // For barbell exercises: working sets + max rep attempt
                $maxRepTarget = $config['max_rep_target'];
                
                // Variable sets and reps for working sets
                $numSets = rand(3, 6);
                $baseReps = 5;
                
                // Add variation in rep ranges based on phase
                if ($progress < 0.25) {
                    $baseReps += rand(0, 3); // Higher reps for beginners
                } elseif ($progress > 0.75) {
                    $baseReps += rand(-2, 1); // Lower reps for advanced
                }

                for ($set = 0; $set < $numSets; $set++) {
                    $reps = $baseReps + rand(-2, 2);
                    
                    LiftSet::create([
                        'lift_log_id' => $liftLog->id,
                        'weight' => $weight,
                        'reps' => max(1, $reps),
                        'notes' => null,
                        'band_color' => null,
                    ]);
                }
                }
            }
        }

        $this->info("  Created {$logsCreated} lift logs across " . count($exercises) . " exercises");
        
        return $exerciseModels;
    }

    private function createWorkouts(User $user, array $exerciseModels)
    {
        // Workout 1: Strength Day
        $strengthWorkout = \App\Models\Workout::create([
            'user_id' => $user->id,
            'name' => 'Strength Day',
            'description' => 'Heavy compound lifts focusing on strength',
            'notes' => 'Rest 3-5 minutes between sets',
            'is_public' => false,
            'tags' => ['strength', 'compound'],
            'times_used' => 0,
        ]);

        $order = 1;
        foreach (['Back Squat', 'Bench Press', 'Deadlift'] as $exerciseName) {
            if (isset($exerciseModels[$exerciseName])) {
                \App\Models\WorkoutExercise::create([
                    'workout_id' => $strengthWorkout->id,
                    'exercise_id' => $exerciseModels[$exerciseName]['model']->id,
                    'order' => $order++,
                ]);
            }
        }

        // Workout 2: Olympic Lifting
        $olympicWorkout = \App\Models\Workout::create([
            'user_id' => $user->id,
            'name' => 'Olympic Lifting',
            'description' => 'Technical Olympic lifts and accessories',
            'notes' => 'Focus on technique and speed',
            'is_public' => false,
            'tags' => ['olympic', 'technique'],
            'times_used' => 0,
        ]);

        $order = 1;
        foreach (['Snatch', 'Clean & Jerk', 'Back Squat', 'Strict Press'] as $exerciseName) {
            if (isset($exerciseModels[$exerciseName])) {
                \App\Models\WorkoutExercise::create([
                    'workout_id' => $olympicWorkout->id,
                    'exercise_id' => $exerciseModels[$exerciseName]['model']->id,
                    'order' => $order++,
                ]);
            }
        }

        // Workout 3: Conditioning & Accessories
        $conditioningWorkout = \App\Models\Workout::create([
            'user_id' => $user->id,
            'name' => 'Conditioning & Accessories',
            'description' => 'Cardio, bodyweight, and accessory work',
            'notes' => 'Keep rest periods short',
            'is_public' => false,
            'tags' => ['conditioning', 'accessories'],
            'times_used' => 0,
        ]);

        $order = 1;
        foreach (['Rowing', 'Pull-ups', 'Push-ups', 'Banded Pull-Down'] as $exerciseName) {
            if (isset($exerciseModels[$exerciseName])) {
                \App\Models\WorkoutExercise::create([
                    'workout_id' => $conditioningWorkout->id,
                    'exercise_id' => $exerciseModels[$exerciseName]['model']->id,
                    'order' => $order++,
                ]);
            }
        }

        $this->info("  Created 3 workouts");
    }
}
