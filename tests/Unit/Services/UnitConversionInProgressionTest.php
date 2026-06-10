<?php

namespace Tests\Unit\Services;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use App\Services\OneRepMaxCalculatorService;
use App\Services\ProgressionModels\DoubleProgression;
use App\Services\ProgressionModels\LinearProgression;
use App\Services\ExerciseTypes\RegularExerciseType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitConversionInProgressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create();
    }

    public function test_double_progression_suggests_in_kg_when_previous_was_lbs(): void
    {
        // User prefers kg
        $this->user->update(['weight_unit' => 'kg']);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        // Previous lift was 100 lbs for 12 reps (max reps, should trigger weight increase)
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'unit' => 'lbs',
            'reps' => 12,
        ]);

        $progressionModel = new DoubleProgression(app(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        // 100 lbs converted to kg is 45.5 kg
        // Increment for kg is 2.5 kg. Suggested weight should be 45.5 + 2.5 = 48.0 kg
        $this->assertEquals(48.0, $suggestion->suggestedWeight);
        $this->assertEquals(8, $suggestion->reps); // Resets to MIN_REPS (8)
    }

    public function test_double_progression_suggests_in_lbs_when_previous_was_kg(): void
    {
        // User prefers lbs
        $this->user->update(['weight_unit' => 'lbs']);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        // Previous lift was 50 kg for 12 reps (max reps, should trigger weight increase)
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 50,
            'unit' => 'kg',
            'reps' => 12,
        ]);

        $progressionModel = new DoubleProgression(app(OneRepMaxCalculatorService::class));
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        // 50 kg converted to lbs is 110 lbs
        // Increment for lbs is 5 lbs. Suggested weight should be 110 + 5 = 115 lbs
        $this->assertEquals(115.0, $suggestion->suggestedWeight);
        $this->assertEquals(8, $suggestion->reps); // Resets to MIN_REPS (8)
    }

    public function test_linear_progression_suggests_in_kg_when_previous_was_lbs(): void
    {
        // User prefers kg
        $this->user->update(['weight_unit' => 'kg']);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        // Previous lift: 100 lbs for 5 reps (higher than target reps of 5 should trigger weight increase)
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'unit' => 'lbs',
            'reps' => 5,
        ]);

        // Mock OneRepMaxCalculatorService to simulate 1RM estimation
        $mockOneRepMax = $this->createMock(OneRepMaxCalculatorService::class);
        
        // 100 lbs converts to 45.5 kg
        // mock calculateOneRepMax with converted weight (45.5 kg)
        $mockOneRepMax->expects($this->any())
            ->method('calculateOneRepMax')
            ->with(45.5, 5)
            ->willReturn(53.0); // 1RM estimate

        $mockOneRepMax->expects($this->any())
            ->method('getWeightFromOneRepMax')
            ->with(53.0, 5)
            ->willReturn(45.5);

        $progressionModel = new LinearProgression($mockOneRepMax);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        // Since reps >= target reps (5 >= 5), increment is added
        // 45.5 + 2.5 (resolution) = 48.0 kg. Ceil to nearest 2.5 kg resolution is 50.0 kg.
        $this->assertEquals(50.0, $suggestion->suggestedWeight);
    }

    public function test_linear_progression_suggests_in_lbs_when_previous_was_kg(): void
    {
        // User prefers lbs
        $this->user->update(['weight_unit' => 'lbs']);

        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);

        // Previous lift: 50 kg for 5 reps
        LiftSet::factory()->count(3)->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 50,
            'unit' => 'kg',
            'reps' => 5,
        ]);

        // Mock OneRepMaxCalculatorService to simulate 1RM estimation
        $mockOneRepMax = $this->createMock(OneRepMaxCalculatorService::class);
        
        // 50 kg converts to 110 lbs
        // mock calculateOneRepMax with converted weight (110 lbs)
        $mockOneRepMax->expects($this->any())
            ->method('calculateOneRepMax')
            ->with(110.0, 5)
            ->willReturn(128.0); // 1RM estimate

        $mockOneRepMax->expects($this->any())
            ->method('getWeightFromOneRepMax')
            ->with(128.0, 5)
            ->willReturn(110.0);

        $progressionModel = new LinearProgression($mockOneRepMax);
        $suggestion = $progressionModel->suggest($this->user->id, $this->exercise->id);

        $this->assertNotNull($suggestion);
        // 110 + 5 (resolution) = 115 lbs.
        $this->assertEquals(115.0, $suggestion->suggestedWeight);
    }

    public function test_infer_progression_model_from_history_with_mixed_units(): void
    {
        // Setup logs:
        // Older log: 100 kg for 5 reps
        // Newer log: 220 lbs for 8 reps
        // Since 100 kg is converted to 220 lbs, weight change is 0. Reps increased from 5 to 8.
        // This should infer DoubleProgression.

        $olderLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now()->subDays(5),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $olderLog->id,
            'weight' => 100,
            'unit' => 'kg',
            'reps' => 5,
        ]);

        $newerLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::now(),
        ]);
        LiftSet::factory()->create([
            'lift_log_id' => $newerLog->id,
            'weight' => 220,
            'unit' => 'lbs',
            'reps' => 8,
        ]);

        $strategy = new RegularExerciseType();
        $method = new \ReflectionMethod(RegularExerciseType::class, 'inferProgressionModelFromHistory');
        $method->setAccessible(true);

        $model = $method->invoke($strategy, $this->user->id, $this->exercise->id, app(OneRepMaxCalculatorService::class));

        $this->assertInstanceOf(DoubleProgression::class, $model);
    }
}
