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
    protected $signature = 'demo:create-user {--fresh : Delete existing demo user and create fresh}';
    protected $description = 'Create a demo user with sample lift logs and body measurements';

    public function handle()
    {
        $fresh = $this->option('fresh');
        
        // Check if demo user already exists
        $existingUser = User::where('email', 'demo@example.com')->first();
        
        if ($existingUser) {
            if ($fresh) {
                $this->info('Deleting existing demo user...');
                $existingUser->forceDelete();
            } else {
                $this->error('Demo user already exists. Use --fresh flag to recreate.');
                return 1;
            }
        }

        $this->info('Creating demo user...');
        
        // Create demo user
        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('demo'),
            'show_global_exercises' => true,
            'show_extra_weight' => false,
            'prefill_suggested_values' => true,
            'show_recommended_exercises' => true,
        ]);

        $this->info("✅ Demo user created (ID: {$user->id})");

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
        $this->createLiftLogs($user);

        $this->info('');
        $this->info('🎉 Demo user setup complete!');
        $this->info('');
        $this->info('Login credentials:');
        $this->info('  Email: demo@example.com');
        $this->info('  Password: demo');
        
        return 0;
    }

    private function createBodyMeasurements(User $user, MeasurementType $weightType, MeasurementType $waistType)
    {
        $startWeight = 180;
        $startWaist = 36;
        $days = 180; // 2x the data points (6 months)
        $interval = 3; // Every 3 days instead of 7

        $currentWeight = $startWeight;
        $currentWaist = $startWaist;

        for ($i = 0; $i <= $days; $i += $interval) {
            $date = Carbon::now()->subDays($days - $i);
            $progress = $i / $days;

            // Create phases with different rates of change
            if ($progress < 0.2) {
                // Initial phase: rapid progress
                $weightChange = -0.15 + (rand(-10, 10) / 100);
                $waistChange = -0.04 + (rand(-5, 5) / 100);
            } elseif ($progress < 0.5) {
                // Stagnation phase: minimal progress
                $weightChange = -0.02 + (rand(-15, 15) / 100);
                $waistChange = -0.005 + (rand(-8, 8) / 100);
            } elseif ($progress < 0.7) {
                // Acceleration phase: renewed progress
                $weightChange = -0.12 + (rand(-8, 8) / 100);
                $waistChange = -0.035 + (rand(-6, 6) / 100);
            } else {
                // Maintenance phase: slight fluctuations
                $weightChange = rand(-20, 5) / 100;
                $waistChange = rand(-10, 5) / 100;
            }

            $currentWeight += $weightChange;
            $currentWaist += $waistChange;

            // Keep values in reasonable ranges
            $currentWeight = max(160, min(185, $currentWeight));
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

        $dataPoints = floor($days / $interval) + 1;
        $this->info("  Created {$dataPoints} data points over {$days} days");
    }

    private function createLiftLogs(User $user)
    {
        // Get or create common exercises with tracking state and max rep targets
        $exercises = [
            'Squat' => ['start_weight' => 135, 'base_progression' => 5, 'current_weight' => 135, 'max_rep_target' => 1],
            'Bench Press' => ['start_weight' => 135, 'base_progression' => 5, 'current_weight' => 135, 'max_rep_target' => 2],
            'Deadlift' => ['start_weight' => 185, 'base_progression' => 10, 'current_weight' => 185, 'max_rep_target' => 1],
            'Overhead Press' => ['start_weight' => 75, 'base_progression' => 5, 'current_weight' => 75, 'max_rep_target' => 3],
            'Pull-ups' => ['start_weight' => 0, 'base_progression' => 0, 'type' => 'bodyweight', 'current_weight' => 0],
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

        // Create lift logs over the past 24 weeks (2x data, 3-4 workouts per week)
        $weeks = 24;
        $totalWorkouts = $weeks * 3.5; // Average 3.5 workouts per week
        $totalWorkouts = (int) $totalWorkouts;

        for ($i = 0; $i < $totalWorkouts; $i++) {
            $daysAgo = ($totalWorkouts - $i) * 1.5; // More frequent workouts
            $date = Carbon::now()->subDays($daysAgo);
            $progress = $i / $totalWorkouts;

            // Rotate through exercises
            $exerciseNames = array_keys($exercises);
            $exerciseName = $exerciseNames[$i % count($exerciseNames)];
            $exerciseData = &$exerciseModels[$exerciseName];
            $exercise = $exerciseData['model'];
            $config = &$exerciseData['config'];

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

            // Check if this is a bodyweight exercise (Pull-ups)
            $isBodyweight = $exercise->exercise_type === 'bodyweight';
            
            if ($isBodyweight) {
                // For pull-ups: just log 5 sets with varying reps
                $numSets = 5;
                $baseReps = 8;
                
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
                
                // Create a max rep attempt log (1RM, 2RM, or 3RM) for barbell exercises
                $maxLiftLog = LiftLog::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exercise->id,
                    'logged_at' => $date->copy()->addMinutes(30),
                    'comments' => "{$maxRepTarget}RM attempt",
                ]);
                
                // Calculate max weight (higher than working weight)
                $maxWeight = round(($weight * 1.15) / 5) * 5;
                
                LiftSet::create([
                    'lift_log_id' => $maxLiftLog->id,
                    'weight' => $maxWeight,
                    'reps' => $maxRepTarget,
                    'notes' => null,
                    'band_color' => null,
                ]);
            }
        }

        $this->info("  Created {$totalWorkouts} lift logs across " . count($exercises) . " exercises");
    }
}
