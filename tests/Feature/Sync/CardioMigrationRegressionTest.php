<?php

namespace Tests\Feature\Sync;

use App\Actions\LiftLogs\CreateLiftLogAction;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use App\Services\Charts\CardioProgressionChartGenerator;
use App\Services\Charts\VolumeProgressionChartGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CardioMigrationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $cardioExercise;
    private Exercise $barbellExercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a cardio exercise
        $this->cardioExercise = Exercise::create([
            'title' => 'Run',
            'canonical_name' => 'run',
            'exercise_type' => 'cardio',
        ]);

        // Create a regular exercise
        $this->barbellExercise = Exercise::create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'exercise_type' => 'regular',
        ]);
    }

    public function test_cardio_progression_chart_generator_reads_distance_data(): void
    {
        // 1. Create a lift log
        $log = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->cardioExercise->id,
            'logged_at' => now(),
        ]);

        // Create two sets with distance
        $log->liftSets()->create([
            'weight' => 0,
            'unit' => 'lbs',
            'distance' => 500.0,
            'distance_unit' => 'm',
            'reps' => null,
        ]);
        $log->liftSets()->create([
            'weight' => 0,
            'unit' => 'lbs',
            'distance' => 500.0,
            'distance_unit' => 'm',
            'reps' => null,
        ]);

        $generator = new CardioProgressionChartGenerator();
        $chartData = $generator->generate(collect([$log]));

        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        
        // Assert for main dataset
        $dataset = $chartData['datasets'][0];
        $this->assertEquals('Total Distance (m)', $dataset['label']);
        $this->assertCount(1, $dataset['data']);
        
        // Check total distance: 500m + 500m = 1000m total
        $this->assertEquals(1000, $dataset['data'][0]['y']);
    }

    public function test_end_to_end_cardio_logging_via_action(): void
    {
        $action = app(CreateLiftLogAction::class);

        $request = new Request([
            'exercise_id' => $this->cardioExercise->id,
            'reps' => '1200', // Distance in meters for cardio
            'rounds' => '2',
            'weight' => '0',
            'date' => now()->toDateString(),
            'time_of_day' => '12:00 PM',
        ]);

        $result = $action->execute($request, $this->user);

        $liftLog = $result['liftLog'];
        $this->assertNotNull($liftLog);
        
        // Load liftSets
        $sets = $liftLog->liftSets;
        $this->assertCount(2, $sets);

        foreach ($sets as $set) {
            $this->assertEquals(1200.0, $set->distance);
            $this->assertEquals('m', $set->distance_unit);
            $this->assertNull($set->reps);
            $this->assertEquals(0, $set->weight);
        }
    }

    public function test_volume_progression_chart_generator_handles_null_reps_for_cardio(): void
    {
        $log = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->cardioExercise->id,
            'logged_at' => now(),
        ]);

        $log->liftSets()->create([
            'weight' => 0,
            'unit' => 'lbs',
            'distance' => 1000.0,
            'distance_unit' => 'm',
            'reps' => null,
        ]);

        $generator = new VolumeProgressionChartGenerator();
        $chartData = $generator->generate(collect([$log]));

        $this->assertIsArray($chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertEquals(0, $chartData['datasets'][0]['data'][0]['y']); // Reps sum is 0
    }

    public function test_non_cardio_exercises_unaffected(): void
    {
        $action = app(CreateLiftLogAction::class);

        $request = new Request([
            'exercise_id' => $this->barbellExercise->id,
            'reps' => '5',
            'rounds' => '3',
            'weight' => '135',
            'date' => now()->toDateString(),
            'time_of_day' => '12:00 PM',
        ]);

        $result = $action->execute($request, $this->user);

        $liftLog = $result['liftLog'];
        $sets = $liftLog->liftSets;
        $this->assertCount(3, $sets);

        foreach ($sets as $set) {
            $this->assertEquals(5, $set->reps);
            $this->assertEquals(135.0, $set->weight);
            $this->assertNull($set->distance);
            $this->assertNull($set->distance_unit);
        }
    }
}
