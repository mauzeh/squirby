<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exercise;

class GlobalExercisesSeeder extends Seeder
{
    protected $console;
    
    public function __construct($console = null)
    {
        $this->console = $console;
    }
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $exercises = $this->getHardcodedExercises();
            $processedCount = 0;
            
            foreach ($exercises as $exerciseData) {
                // Check if exercise exists to determine if it's new or updated
                $existingExercise = Exercise::where('title', $exerciseData['title'])
                    ->whereNull('user_id')
                    ->first();
                
                $exerciseModel = Exercise::updateOrCreate(
                    ['title' => $exerciseData['title'], 'user_id' => null],
                    $exerciseData
                );
                
                // Output changes if console is available
                if ($this->console) {
                    if (!$existingExercise) {
                        $this->console->line("Created: {$exerciseData['title']}");
                    } else {
                        // Check what changed
                        $changes = [];
                        if ($existingExercise->canonical_name !== $exerciseData['canonical_name']) {
                            $changes[] = "canonical_name: '{$existingExercise->canonical_name}' → '{$exerciseData['canonical_name']}'";
                        }
                        if ($existingExercise->description !== $exerciseData['description']) {
                            $changes[] = "description updated";
                        }
                        if (($existingExercise->is_bodyweight ?? false) !== ($exerciseData['is_bodyweight'] ?? false)) {
                            $changes[] = "is_bodyweight: " . ($existingExercise->is_bodyweight ? 'true' : 'false') . " → " . ($exerciseData['is_bodyweight'] ? 'true' : 'false');
                        }
                        if (($existingExercise->band_type ?? null) !== ($exerciseData['band_type'] ?? null)) {
                            $oldBandType = $existingExercise->band_type ?? 'null';
                            $newBandType = $exerciseData['band_type'] ?? 'null';
                            $changes[] = "band_type: '{$oldBandType}' → '{$newBandType}'";
                        }
                        
                        if (!empty($changes)) {
                            $this->console->line("Updated: {$exerciseData['title']} (" . implode(', ', $changes) . ")");
                        }
                    }
                }
                
                $processedCount++;
            }
            
            if ($this->console) {
                $this->console->info("Successfully processed {$processedCount} exercises from hardcoded data");
            }
        } catch (\Exception $e) {
            if ($this->console) {
                $this->console->error('Failed to seed exercises: ' . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Get hardcoded exercise dataset covering major muscle groups
     * 
     * @return array
     */
    private function getHardcodedExercises(): array
    {
        return [
            // Upper Body - Chest
            [
                'title' => 'Push-Up',
                'description' => 'A bodyweight exercise that targets the chest, shoulders, and triceps.',
                'is_bodyweight' => true,
                'canonical_name' => 'push_up',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Bench Press',
                'description' => 'A compound exercise that targets the muscles of the upper body, including the chest, shoulders, and triceps.',
                'is_bodyweight' => false,
                'canonical_name' => 'bench_press',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Upper Body - Back
            [
                'title' => 'Pull-Up',
                'description' => 'A bodyweight pulling exercise with pronated grip that targets the back and biceps.',
                'is_bodyweight' => true,
                'canonical_name' => 'pull_ups',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Chin-Up',
                'description' => 'A bodyweight pulling exercise with supinated grip that targets the back and biceps.',
                'is_bodyweight' => true,
                'canonical_name' => 'chin_ups',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Bent-Over Row',
                'description' => 'A compound pulling exercise that targets the back muscles and rear deltoids.',
                'is_bodyweight' => false,
                'canonical_name' => 'bent_over_row',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Lat Pull-Down',
                'description' => 'A machine exercise that targets the latissimus dorsi and other back muscles.',
                'is_bodyweight' => false,
                'canonical_name' => 'lat_pull_down',
                'user_id' => null,
                'band_type' => 'resistance',
            ],
            
            // Upper Body - Shoulders
            [
                'title' => 'Strict Press',
                'description' => 'An overhead pressing movement that targets the shoulders and triceps.',
                'is_bodyweight' => false,
                'canonical_name' => 'strict_press',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Pike Push-Up',
                'description' => 'A bodyweight exercise that targets the shoulders and upper chest.',
                'is_bodyweight' => true,
                'canonical_name' => 'pike_push_up',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Lower Body - Quads/Glutes
            [
                'title' => 'Back Squat',
                'description' => 'A compound exercise that targets the muscles of the legs and core.',
                'is_bodyweight' => false,
                'canonical_name' => 'back_squat',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Front Squat',
                'description' => 'A squat variation with the barbell held in front that emphasizes the quads and core.',
                'is_bodyweight' => false,
                'canonical_name' => 'front_squat',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Bodyweight Squat',
                'description' => 'A fundamental bodyweight exercise that targets the legs and glutes.',
                'is_bodyweight' => true,
                'canonical_name' => 'bodyweight_squat',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Lunge',
                'description' => 'A unilateral exercise that targets the legs and improves balance.',
                'is_bodyweight' => true,
                'canonical_name' => 'lunge',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Lower Body - Posterior Chain
            [
                'title' => 'Deadlift',
                'description' => 'A compound exercise that targets the muscles of the back, legs, and grip.',
                'is_bodyweight' => false,
                'canonical_name' => 'deadlift',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Romanian Deadlift',
                'description' => 'A hip-hinge movement that targets the hamstrings and glutes.',
                'is_bodyweight' => false,
                'canonical_name' => 'romanian_deadlift',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Hip Thrust',
                'description' => 'An exercise that specifically targets the glutes and posterior chain.',
                'is_bodyweight' => false,
                'canonical_name' => 'hip_thrust',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Core
            [
                'title' => 'Plank',
                'description' => 'An isometric core exercise that builds stability and strength.',
                'is_bodyweight' => true,
                'canonical_name' => 'plank',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Mountain Climbers',
                'description' => 'A dynamic bodyweight exercise that targets the core and provides cardio.',
                'is_bodyweight' => true,
                'canonical_name' => 'mountain_climbers',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Dead Bug',
                'description' => 'A core stability exercise that teaches proper spinal alignment.',
                'is_bodyweight' => true,
                'canonical_name' => 'dead_bug',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Full Body/Olympic
            [
                'title' => 'Burpee',
                'description' => 'A full-body exercise that combines a squat, push-up, and jump.',
                'is_bodyweight' => true,
                'canonical_name' => 'burpee',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Power Clean',
                'description' => 'An explosive Olympic lift that targets the entire body.',
                'is_bodyweight' => false,
                'canonical_name' => 'power_clean',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Thruster',
                'description' => 'A combination of front squat and overhead press in one fluid movement.',
                'is_bodyweight' => false,
                'canonical_name' => 'thruster',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Isolation/Accessory
            [
                'title' => 'Bicep Curl',
                'description' => 'An isolation exercise that targets the biceps.',
                'is_bodyweight' => false,
                'canonical_name' => 'bicep_curl',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Tricep Dip',
                'description' => 'A bodyweight exercise that targets the triceps and chest.',
                'is_bodyweight' => true,
                'canonical_name' => 'tricep_dip',
                'user_id' => null,
                'band_type' => null,
            ],
            [
                'title' => 'Calf Raise',
                'description' => 'An isolation exercise that targets the calf muscles.',
                'is_bodyweight' => true,
                'canonical_name' => 'calf_raise',
                'user_id' => null,
                'band_type' => null,
            ],
            
            // Cardio/Conditioning
            [
                'title' => 'Jumping Jacks',
                'description' => 'A cardiovascular exercise that involves jumping while moving arms and legs.',
                'is_bodyweight' => true,
                'canonical_name' => 'jumping_jacks',
                'user_id' => null,
                'band_type' => null,
            ],
        ];
    }
}