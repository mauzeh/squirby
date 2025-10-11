<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Exercise;
use App\Models\User;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogTsvImportWithGlobalExercisesTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService(new \App\Services\IngredientTsvProcessorService());
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_imports_lift_logs_using_global_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Bench Press',
            'description' => 'Global exercise for bench press',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tGlobal Bench Press\t135\t8\t3\tGood form";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2024-01-15', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertEmpty($result['invalidRows']);

        // Verify lift log was created
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals(3, $liftLog->liftSets->count());
        $this->assertEquals('Good form', $liftLog->comments);
    }

    /** @test */
    public function it_imports_lift_logs_using_user_exercises()
    {
        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Squat',
            'description' => 'User-specific squat exercise',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tPersonal Squat\t185\t5\t5\tDeep squats";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2024-01-15', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertEmpty($result['invalidRows']);

        // Verify lift log was created
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals(5, $liftLog->liftSets->count());
        $this->assertEquals('Deep squats', $liftLog->comments);
    }

    /** @test */
    public function it_prioritizes_user_exercise_over_global_when_same_name()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Deadlift',
            'description' => 'Global deadlift exercise',
            'is_bodyweight' => false,
        ]);

        // Create a user exercise with the same name
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Deadlift',
            'description' => 'User-specific deadlift exercise',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tDeadlift\t225\t3\t3\tConventional style";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2024-01-15', $this->user->id);

        $this->assertEquals(1, $result['importedCount']);

        // Verify lift log was created using the user exercise, not the global one
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals($userExercise->id, $liftLog->exercise_id);

        // Verify no lift log was created for the global exercise
        $globalLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNull($globalLiftLog);
    }

    /** @test */
    public function it_handles_mixed_global_and_user_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Pull-ups',
            'description' => 'Global pull-up exercise',
            'is_bodyweight' => true,
        ]);

        // Create a user exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Dips',
            'description' => 'User-specific dips exercise',
            'is_bodyweight' => true,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tGlobal Pull-ups\t0\t10\t3\tBodyweight\n" .
                   "1/15/2024\t6:30 AM\tPersonal Dips\t0\t12\t3\tGood depth";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2024-01-15', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEmpty($result['notFound']);

        // Verify both lift logs were created
        $globalLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $userLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($globalLiftLog);
        $this->assertNotNull($userLiftLog);
        $this->assertEquals('Bodyweight', $globalLiftLog->comments);
        $this->assertEquals('Good depth', $userLiftLog->comments);
    }
}