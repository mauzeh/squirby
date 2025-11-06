<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Services\ProgressionModels\DoubleProgression;
use App\Services\OneRepMaxCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class DoubleProgressionBodyweightTest extends TestCase
{
    use RefreshDatabase;

    private DoubleProgression $doubleProgression;
    private User $user;
    private Exercise $bodyweightExercise;
    private Exercise $weightedExercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $mockOneRepMaxService = $this->createMock(OneRepMaxCalculatorService::class);
        $this->doubleProgression = new DoubleProgression($mockOneRepMaxService);
        
        $this->user = User::factory()->create([
            'show_extra_weight' => false
        ]);
        
        $this->bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
        ]);
        
        $this->weightedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
        ]);
    }

    public function test_bodyweight_exercise_continues_progressing_reps_when_preference_disabled()
    {
        // Create a lift log with 20 reps (above MAX_REPS of 12)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        
        LiftSet::factory()->count(5)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 20,
        ]);

        $suggestion = $this->doubleProgression->suggest($this->user->id, $this->bodyweightExercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(0, $suggestion->suggestedWeight); // No added weight
        $this->assertEquals(21, $suggestion->reps); // Continue progressing reps
        $this->assertEquals(5, $suggestion->sets);
    }

    public function test_bodyweight_exercise_suggests_added_weight_when_preference_enabled()
    {
        // Enable show_extra_weight preference
        $this->user->update(['show_extra_weight' => true]);
        
        // Create a lift log with 12 reps (at MAX_REPS)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 12,
        ]);

        $suggestion = $this->doubleProgression->suggest($this->user->id, $this->bodyweightExercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(5, $suggestion->suggestedWeight); // Add 5 lbs
        $this->assertEquals(8, $suggestion->reps); // Drop to MIN_REPS
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_bodyweight_exercise_continues_reps_even_with_preference_enabled_below_max()
    {
        // Enable show_extra_weight preference
        $this->user->update(['show_extra_weight' => true]);
        
        // Create a lift log with 10 reps (below MAX_REPS of 12)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10,
        ]);

        $suggestion = $this->doubleProgression->suggest($this->user->id, $this->bodyweightExercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(0, $suggestion->suggestedWeight); // No added weight yet
        $this->assertEquals(11, $suggestion->reps); // Continue progressing reps
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_weighted_exercise_behavior_unchanged()
    {
        // Create a lift log with 12 reps (at MAX_REPS)
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->weightedExercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 135,
            'reps' => 12,
        ]);

        $suggestion = $this->doubleProgression->suggest($this->user->id, $this->weightedExercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(140, $suggestion->suggestedWeight); // Add 5 lbs
        $this->assertEquals(8, $suggestion->reps); // Drop to MIN_REPS
        $this->assertEquals(3, $suggestion->sets);
    }

    public function test_bodyweight_exercise_with_existing_added_weight()
    {
        // User has show_extra_weight disabled but already has some added weight
        $this->user->update(['show_extra_weight' => false]);
        
        // Create a lift log with 15 reps and 10 lbs added weight
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
        
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 10,
            'reps' => 15,
        ]);

        $suggestion = $this->doubleProgression->suggest($this->user->id, $this->bodyweightExercise->id);

        $this->assertNotNull($suggestion);
        $this->assertEquals(10, $suggestion->suggestedWeight); // Keep same weight
        $this->assertEquals(16, $suggestion->reps); // Continue progressing reps
        $this->assertEquals(3, $suggestion->sets);
    }
}