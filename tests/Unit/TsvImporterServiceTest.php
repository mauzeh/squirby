<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Ingredient;
use App\Models\Exercise;
use App\Models\DailyLog;
use App\Models\LiftLog;
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
    public function it_imports_food_logs_correctly()
    {
        $unit = Unit::factory()->create(['name' => 'grams', 'abbreviation' => 'g']);
        $ingredient1 = Ingredient::factory()->create(['name' => 'Apple', 'base_unit_id' => $unit->id]);
        $ingredient2 = Ingredient::factory()->create(['name' => 'Banana', 'base_unit_id' => $unit->id]);

        $tsvData = "2025-08-26\t10:00\tApple\tNote 1\t100\n" .
                   "2025-08-26\t12:00\tBanana\tNote 2\t150";

        $result = $this->tsvImporterService->importFoodLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['notFound']);

        $this->assertDatabaseCount('food_logs', 2);

        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 100,
            'notes' => 'Note 1',
            'logged_at' => '2025-08-26 10:00:00',
        ]);

        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 150,
            'notes' => 'Note 2',
            'logged_at' => '2025-08-26 12:00:00',
        ]);
    }

    /** @test */
    public function it_handles_not_found_ingredients_when_importing_food_logs()
    {
        $tsvData = "2025-08-26\t10:00\tNonExistentIngredient\tNote 1\t100";

        $result = $this->tsvImporterService->importFoodLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(['NonExistentIngredient'], $result['notFound']);
        $this->assertDatabaseCount('food_logs', 0);
    }

    /** @test */
    public function it_handles_empty_tsv_data_for_food_logs()
    {
        $result = $this->tsvImporterService->importFoodLogs('', '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertDatabaseCount('food_logs', 0);
    }

    /** @test */
    public function it_imports_lift_logs_correctly()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Push Ups']);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Squats']);

        $tsvData = "2025-08-26\t08:00\tPush Ups\t10\t3\t15\tWarm up\n" .
                   "2025-08-26\t08:30\tSquats\t50\t5\t10\tMain set";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['notFound']);

        $this->assertDatabaseCount('lift_logs', 2);
        $this->assertDatabaseCount('lift_sets', 25); // 15 for Push Ups + 10 for Squats

        $liftLog1 = \App\Models\LiftLog::where('exercise_id', $exercise1->id)->first();
        $liftLog2 = \App\Models\LiftLog::where('exercise_id', $exercise2->id)->first();

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'comments' => 'Warm up',
            'logged_at' => '2025-08-26 08:00:00',
        ]);

        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog1->id,
            'weight' => 10,
            'reps' => 3,
            'notes' => 'Warm up',
        ]);

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'comments' => 'Main set',
            'logged_at' => '2025-08-26 08:30:00',
        ]);

        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog2->id,
            'weight' => 50,
            'reps' => 5,
            'notes' => 'Main set',
        ]);
    }

    /** @test */
    public function it_handles_not_found_exercises_when_importing_lift_logs()
    {
        $tsvData = "2025-08-26\t08:00\tNonExistentExercise\t10\t3\t15\tWarm up";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(['NonExistentExercise'], $result['notFound']);
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function it_handles_empty_tsv_data_for_lift_logs()
    {
        $result = $this->tsvImporterService->importLiftLogs('', '2025-08-26', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEmpty($result['notFound']);
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function it_imports_measurements_correctly()
    {
        $tsvData = "09/07/2025\t10:00\tBodyweight\t180\tlbs\tMorning weight\n"
                 . "09/07/2025\t12:00\tWaist\t32\tin\tPost lunch";

        $result = $this->tsvImporterService->importMeasurements($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);

        $this->assertDatabaseCount('body_logs', 2);
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'value' => 180,
            'comments' => 'Morning weight',
        ]);
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $this->user->id,
            'value' => 32,
            'comments' => 'Post lunch',
        ]);
    }

    /** @test */
    public function it_handles_empty_tsv_data_for_measurements()
    {
        $result = $this->tsvImporterService->importMeasurements('', $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertDatabaseCount('body_logs', 0);
    }

    /** @test */
    public function it_handles_invalid_rows_when_importing_measurements()
    {
        $tsvData = "invalid row";

        $result = $this->tsvImporterService->importMeasurements($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(['invalid row'], $result['invalidRows']);
        $this->assertDatabaseCount('body_logs', 0);
    }
}