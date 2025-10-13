<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TransformationDataGenerator;
use App\Services\TransformationConfig;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransformationDataGeneratorWorkoutTest extends TestCase
{
    use RefreshDatabase;

    private TransformationDataGenerator $generator;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TransformationDataGenerator();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create basic unit for exercises
        Unit::factory()->create(['abbreviation' => 'g', 'name' => 'grams']);
    }

    public function test_generates_workout_programs_for_strength_program()
    {
        $startDate = Carbon::now()->subWeeks(12);
        $weeks = 12;
        $programType = 'strength';
        
        $programs = $this->generator->generateWorkoutPrograms($startDate, $weeks, $programType, $this->user->id);
        
        // Should generate programs for 12 weeks
        $this->assertNotEmpty($programs);
        
        // Check that programs have required fields
        $firstProgram = $programs[0];
        $this->assertArrayHasKey('user_id', $firstProgram);
        $this->assertArrayHasKey('exercise_id', $firstProgram);
        $this->assertArrayHasKey('date', $firstProgram);
        $this->assertArrayHasKey('sets', $firstProgram);
        $this->assertArrayHasKey('reps', $firstProgram);
        $this->assertArrayHasKey('comments', $firstProgram);
        $this->assertArrayHasKey('priority', $firstProgram);
        
        $this->assertEquals($this->user->id, $firstProgram['user_id']);
        $this->assertGreaterThan(0, $firstProgram['sets']);
        $this->assertGreaterThan(0, $firstProgram['reps']);
    }

    public function test_generates_lift_logs_with_progressive_overload()
    {
        $startDate = Carbon::now()->subWeeks(12);
        $weeks = 12;
        
        // First generate programs
        $programs = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        
        // Then generate lift logs
        $liftLogs = $this->generator->generateLiftLogs($programs, $startDate, $weeks, $this->user->id);
        
        $this->assertNotEmpty($liftLogs);
        
        // Check lift log structure
        $firstLiftLog = $liftLogs[0];
        $this->assertArrayHasKey('user_id', $firstLiftLog);
        $this->assertArrayHasKey('exercise_id', $firstLiftLog);
        $this->assertArrayHasKey('logged_at', $firstLiftLog);
        $this->assertArrayHasKey('sets_data', $firstLiftLog);
        
        $this->assertEquals($this->user->id, $firstLiftLog['user_id']);
        $this->assertNotEmpty($firstLiftLog['sets_data']);
        
        // Check sets data structure
        $firstSet = $firstLiftLog['sets_data'][0];
        $this->assertArrayHasKey('weight', $firstSet);
        $this->assertArrayHasKey('reps', $firstSet);
        $this->assertArrayHasKey('set_type', $firstSet);
    }

    public function test_creates_exercises_if_they_dont_exist()
    {
        $startDate = Carbon::now()->subWeeks(4);
        $weeks = 4;
        
        // Ensure no exercises exist initially
        $this->assertEquals(0, Exercise::count());
        
        $programs = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        
        // Should have created exercises
        $this->assertGreaterThan(0, Exercise::count());
        
        // All programs should reference valid exercises
        foreach ($programs as $program) {
            $exercise = Exercise::find($program['exercise_id']);
            $this->assertNotNull($exercise);
            $this->assertEquals($this->user->id, $exercise->user_id);
        }
    }

    public function test_applies_realistic_variations_including_missed_workouts()
    {
        $startDate = Carbon::now()->subWeeks(4);
        $weeks = 4;
        
        // Generate programs and lift logs
        $programs = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        $liftLogs = $this->generator->generateLiftLogs($programs, $startDate, $weeks, $this->user->id);
        
        $originalCount = count($liftLogs);
        
        // Apply variations with high missed workout rate for testing
        $variatedLiftLogs = $this->generator->applyLiftLogVariations($liftLogs, 0.3);
        
        // Should have fewer lift logs due to missed workouts
        $this->assertLessThanOrEqual($originalCount, count($variatedLiftLogs));
        
        // Remaining lift logs should have sets data
        foreach ($variatedLiftLogs as $liftLog) {
            $this->assertNotEmpty($liftLog['sets_data']);
        }
    }

    public function test_generates_different_programs_for_different_types()
    {
        $startDate = Carbon::now()->subWeeks(2);
        $weeks = 2;
        
        $strengthPrograms = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        $powerliftingPrograms = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'powerlifting', $this->user->id);
        $bodybuildingPrograms = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'bodybuilding', $this->user->id);
        
        // Should generate different numbers of exercises for different program types
        $this->assertNotEmpty($strengthPrograms);
        $this->assertNotEmpty($powerliftingPrograms);
        $this->assertNotEmpty($bodybuildingPrograms);
        
        // Bodybuilding should have more exercises than strength
        $this->assertGreaterThan(count($strengthPrograms), count($bodybuildingPrograms));
    }

    public function test_strength_progression_increases_over_weeks()
    {
        $startWeight = 135;
        $weeks = 12;
        $exerciseType = 'squat';
        
        $progression = $this->generator->calculateStrengthProgression($startWeight, $weeks, $exerciseType);
        
        $this->assertCount($weeks, $progression);
        $this->assertEquals($startWeight, $progression[0]);
        
        // Should show progression over time
        $finalWeight = end($progression);
        $this->assertGreaterThan($startWeight, $finalWeight);
    }

    public function test_generates_warmup_sets_for_compound_movements()
    {
        $startDate = Carbon::now()->subWeeks(1);
        $weeks = 1;
        
        // Generate programs and lift logs
        $programs = $this->generator->generateWorkoutPrograms($startDate, $weeks, 'strength', $this->user->id);
        $liftLogs = $this->generator->generateLiftLogs($programs, $startDate, $weeks, $this->user->id);
        
        // Find a compound movement lift log
        $compoundLiftLog = null;
        foreach ($liftLogs as $liftLog) {
            $exercise = Exercise::find($liftLog['exercise_id']);
            if ($exercise && str_contains(strtolower($exercise->title), 'squat')) {
                $compoundLiftLog = $liftLog;
                break;
            }
        }
        
        $this->assertNotNull($compoundLiftLog);
        
        // Should have both warmup and working sets
        $hasWarmup = false;
        $hasWorking = false;
        
        foreach ($compoundLiftLog['sets_data'] as $set) {
            if ($set['set_type'] === 'warmup') $hasWarmup = true;
            if ($set['set_type'] === 'working') $hasWorking = true;
        }
        
        $this->assertTrue($hasWarmup, 'Should have warmup sets');
        $this->assertTrue($hasWorking, 'Should have working sets');
    }
}