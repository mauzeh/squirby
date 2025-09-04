<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Ingredient;
use App\Models\Exercise;
use App\Models\DailyLog;
use App\Models\Workout;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TsvImporterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_imports_daily_logs_correctly()
    {
        $unit = Unit::factory()->create(['name' => 'grams', 'abbreviation' => 'g']);
        $ingredient1 = Ingredient::factory()->create(['name' => 'Apple', 'base_unit_id' => $unit->id]);
        $ingredient2 = Ingredient::factory()->create(['name' => 'Banana', 'base_unit_id' => $unit->id]);

        $tsvData = "2025-08-26\t10:00\tApple\tNote 1\t100\n" .
                   "2025-08-26\t12:00\tBanana\tNote 2\t150";

        $result = $this->tsvImporterService->importDailyLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['notFound']);

        $this->assertDatabaseCount('daily_logs', 2);

        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 100,
            'notes' => 'Note 1',
            'logged_at' => '2025-08-26 10:00:00',
        ]);

        $this->assertDatabaseHas('daily_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 150,
            'notes' => 'Note 2',
            'logged_at' => '2025-08-26 12:00:00',
        ]);
    }

    /** @test */
    public function it_handles_not_found_ingredients_when_importing_daily_logs()
    {
        $tsvData = "2025-08-26\t10:00\tNonExistentIngredient\tNote 1\t100";

        $result = $this->tsvImporterService->importDailyLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(['NonExistentIngredient'], $result['notFound']);
        $this->assertDatabaseCount('daily_logs', 0);
    }

    /** @test */
    public function it_handles_empty_tsv_data_for_daily_logs()
    {
        $result = $this->tsvImporterService->importDailyLogs('', '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertDatabaseCount('daily_logs', 0);
    }

    /** @test */
    public function it_imports_workouts_correctly()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Push Ups']);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Squats']);

        $tsvData = "2025-08-26\t08:00\tPush Ups\t10\t3\t15\tWarm up\n" .
                   "2025-08-26\t08:30\tSquats\t50\t5\t10\tMain set";

        $result = $this->tsvImporterService->importWorkouts($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['notFound']);

        $this->assertDatabaseCount('workouts', 2);
        $this->assertDatabaseCount('workout_sets', 25); // 15 for Push Ups + 10 for Squats

        $workout1 = \App\Models\Workout::where('exercise_id', $exercise1->id)->first();
        $workout2 = \App\Models\Workout::where('exercise_id', $exercise2->id)->first();

        $this->assertDatabaseHas('workouts', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'comments' => 'Warm up',
            'logged_at' => '2025-08-26 08:00:00',
        ]);

        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout1->id,
            'weight' => 10,
            'reps' => 3,
            'notes' => 'Warm up',
        ]);

        $this->assertDatabaseHas('workouts', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'comments' => 'Main set',
            'logged_at' => '2025-08-26 08:30:00',
        ]);

        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout2->id,
            'weight' => 50,
            'reps' => 5,
            'notes' => 'Main set',
        ]);
    }

    /** @test */
    public function it_handles_not_found_exercises_when_importing_workouts()
    {
        $tsvData = "2025-08-26\t08:00\tNonExistentExercise\t10\t3\t15\tWarm up";

        $result = $this->tsvImporterService->importWorkouts($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(['NonExistentExercise'], $result['notFound']);
        $this->assertDatabaseCount('workouts', 0);
    }

    /** @test */
    public function it_handles_empty_tsv_data_for_workouts()
    {
        $result = $this->tsvImporterService->importWorkouts('', '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertDatabaseCount('workouts', 0);
    }
}