<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\RealisticVariationService;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkoutProgramGenerationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TransformationDataGenerator $generator;
    private RealisticVariationService $variationService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
        $this->variationService = new RealisticVariationService();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create basic unit for exercises
        Unit::factory()->create(['abbreviation' => 'g', 'name' => 'grams']);
    }

    public function test_complete_workout_program_and_lift_log_generation_workflow()
    {
        $startDate = Carbon::now()->subWeeks(4);
        $weeks = 4;
        $programType = 'strength';
        
        // Step 1: Generate workout programs
        $programsData = $this->generator->generateWorkoutPrograms($startDate, $weeks, $programType, $this->user->id);
        
        $this->assertNotEmpty($programsData);
        
        // Step 2: Create Program records in database
        $createdPrograms = [];
        foreach ($programsData as $programData) {
            $createdPrograms[] = Program::create($programData);
        }
        
        $this->assertCount(count($programsData), $createdPrograms);
        
        // Step 3: Generate lift logs based on programs
        $liftLogsData = $this->generator->generateLiftLogs($programsData, $startDate, $weeks, $this->user->id);
        
        $this->assertNotEmpty($liftLogsData);
        
        // Step 4: Apply realistic variations
        $variatedLiftLogsData = $this->generator->applyLiftLogVariations($liftLogsData, 0.1);
        
        // Step 5: Create LiftLog and LiftSet records in database
        foreach ($variatedLiftLogsData as $liftLogData) {
            $liftLog = LiftLog::create([
                'user_id' => $liftLogData['user_id'],
                'exercise_id' => $liftLogData['exercise_id'],
                'logged_at' => $liftLogData['logged_at'],
                'comments' => $liftLogData['comments'] ?? ''
            ]);
            
            // Create lift sets
            foreach ($liftLogData['sets_data'] as $setData) {
                LiftSet::create([
                    'lift_log_id' => $liftLog->id,
                    'weight' => $setData['weight'],
                    'reps' => $setData['reps'],
                    'notes' => $setData['notes'] ?? ''
                ]);
            }
        }
        
        // Verify data was created correctly
        $this->assertGreaterThan(0, Program::count());
        $this->assertGreaterThan(0, Exercise::count());
        $this->assertGreaterThan(0, LiftLog::count());
        $this->assertGreaterThan(0, LiftSet::count());
        
        // Verify relationships work
        $firstLiftLog = LiftLog::first();
        $this->assertNotNull($firstLiftLog->exercise);
        $this->assertNotNull($firstLiftLog->user);
        $this->assertGreaterThan(0, $firstLiftLog->liftSets->count());
        
        // Verify progression exists
        $squatExercise = Exercise::where('title', 'like', '%Squat%')->first();
        if ($squatExercise) {
            $squatLiftLogs = LiftLog::where('exercise_id', $squatExercise->id)
                ->orderBy('logged_at')
                ->get();
            
            if ($squatLiftLogs->count() > 1) {
                $firstWorkout = $squatLiftLogs->first();
                $lastWorkout = $squatLiftLogs->last();
                
                $firstWeight = $firstWorkout->liftSets->where('weight', '>', 0)->first()?->weight ?? 0;
                $lastWeight = $lastWorkout->liftSets->where('weight', '>', 0)->first()?->weight ?? 0;
                
                // Should show some progression (allowing for variations)
                $this->assertGreaterThanOrEqual($firstWeight * 0.9, $lastWeight);
            }
        }
    }

    public function test_exercises_are_created_with_proper_attributes()
    {
        $startDate = Carbon::now()->subWeeks(2);
        $weeks = 2;
        
        $programsData = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        
        // Check that exercises were created
        $exercises = Exercise::where('user_id', $this->user->id)->get();
        $this->assertGreaterThan(0, $exercises->count());
        
        // Verify exercise attributes
        foreach ($exercises as $exercise) {
            $this->assertNotEmpty($exercise->title);
            $this->assertNotEmpty($exercise->description);
            $this->assertIsBool($exercise->is_bodyweight);
            $this->assertEquals($this->user->id, $exercise->user_id);
        }
        
        // Check for expected strength training exercises
        $exerciseTitles = $exercises->pluck('title')->toArray();
        $this->assertContains('Barbell Back Squat', $exerciseTitles);
        $this->assertContains('Barbell Bench Press', $exerciseTitles);
        $this->assertContains('Conventional Deadlift', $exerciseTitles);
    }

    public function test_program_periodization_changes_over_weeks()
    {
        $startDate = Carbon::now()->subWeeks(12);
        $weeks = 12;
        
        $programsData = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        
        // Group programs by week
        $programsByWeek = [];
        foreach ($programsData as $program) {
            $week = $startDate->diffInWeeks($program['date']) + 1;
            $programsByWeek[$week][] = $program;
        }
        
        // Check that early weeks have different sets/reps than later weeks
        if (isset($programsByWeek[1]) && isset($programsByWeek[12])) {
            $earlyProgram = $programsByWeek[1][0];
            $lateProgram = $programsByWeek[12][0];
            
            // Later weeks should generally have more sets or different rep ranges
            $this->assertTrue(
                $lateProgram['sets'] >= $earlyProgram['sets'] || 
                $lateProgram['reps'] != $earlyProgram['reps']
            );
        }
    }

    public function test_lift_logs_include_both_warmup_and_working_sets()
    {
        $startDate = Carbon::now()->subWeeks(1);
        $weeks = 1;
        
        // Generate programs and lift logs
        $programsData = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        $liftLogsData = $this->generator->generateLiftLogs($programsData, $startDate, $weeks, $this->user->id);
        
        // Find a compound movement
        $compoundLiftLog = null;
        foreach ($liftLogsData as $liftLogData) {
            $exercise = Exercise::find($liftLogData['exercise_id']);
            if ($exercise && str_contains(strtolower($exercise->title), 'squat')) {
                $compoundLiftLog = $liftLogData;
                break;
            }
        }
        
        $this->assertNotNull($compoundLiftLog);
        
        // Check for warmup and working sets
        $setTypes = array_column($compoundLiftLog['sets_data'], 'set_type');
        $this->assertContains('warmup', $setTypes);
        $this->assertContains('working', $setTypes);
        
        // Warmup sets should have lower weights than working sets
        $warmupWeights = [];
        $workingWeights = [];
        
        foreach ($compoundLiftLog['sets_data'] as $set) {
            if ($set['set_type'] === 'warmup') {
                $warmupWeights[] = $set['weight'];
            } elseif ($set['set_type'] === 'working') {
                $workingWeights[] = $set['weight'];
            }
        }
        
        if (!empty($warmupWeights) && !empty($workingWeights)) {
            $maxWarmupWeight = max($warmupWeights);
            $minWorkingWeight = min($workingWeights);
            $this->assertLessThanOrEqual($minWorkingWeight, $maxWarmupWeight);
        }
    }

    public function test_different_program_types_generate_different_exercise_selections()
    {
        $startDate = Carbon::now()->subWeeks(1);
        $weeks = 1;
        
        $strengthPrograms = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        
        // Clear exercises and create new user for different program type
        Exercise::where('user_id', $this->user->id)->delete();
        $bodybuildingUser = User::factory()->create();
        
        $bodybuildingPrograms = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'bodybuilding', $bodybuildingUser->id);
        
        // Get exercise titles for each program type
        $strengthExerciseIds = array_unique(array_column($strengthPrograms, 'exercise_id'));
        $bodybuildingExerciseIds = array_unique(array_column($bodybuildingPrograms, 'exercise_id'));
        
        $strengthExercises = Exercise::whereIn('id', $strengthExerciseIds)->pluck('title')->toArray();
        $bodybuildingExercises = Exercise::whereIn('id', $bodybuildingExerciseIds)->pluck('title')->toArray();
        
        // Bodybuilding should have more exercise variety
        $this->assertGreaterThan(count($strengthExercises), count($bodybuildingExercises));
        
        // Bodybuilding should include exercises not in strength program
        $bodybuildingSpecific = array_diff($bodybuildingExercises, $strengthExercises);
        $this->assertNotEmpty($bodybuildingSpecific);
    }

    public function test_realistic_variations_affect_performance_appropriately()
    {
        $startDate = Carbon::now()->subWeeks(2);
        $weeks = 2;
        
        // Generate lift logs
        $programsData = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        $liftLogsData = $this->generator->generateLiftLogs($programsData, $startDate, $weeks, $this->user->id);
        
        // Apply variations multiple times to see different results
        $variatedLogs1 = $this->generator->applyLiftLogVariations($liftLogsData, 0.0); // No missed workouts
        $variatedLogs2 = $this->generator->applyLiftLogVariations($liftLogsData, 0.5); // High miss rate
        
        // High miss rate should result in fewer workouts
        $this->assertLessThan(count($variatedLogs1), count($variatedLogs2));
        
        // Check that performance variations are applied
        if (!empty($variatedLogs1)) {
            $originalSets = $liftLogsData[0]['sets_data'];
            $variatedSets = $variatedLogs1[0]['sets_data'];
            
            // Should have same number of sets (no sets removed, just varied)
            $originalWorkingSets = array_filter($originalSets, fn($set) => $set['set_type'] === 'working');
            $variatedWorkingSets = array_filter($variatedSets, fn($set) => $set['set_type'] === 'working');
            
            $this->assertCount(count($originalWorkingSets), $variatedWorkingSets);
        }
    }
}