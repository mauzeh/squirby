<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Models\Ingredient;
use App\Models\Exercise;
use App\Models\DailyLog;
use App\Models\FoodLog;
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
        $processor = new \App\Services\IngredientTsvProcessorService();
        $this->tsvImporterService = new TsvImporterService($processor);
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
    public function it_skips_duplicate_food_logs_during_import()
    {
        $unit = Unit::factory()->create(['name' => 'grams', 'abbreviation' => 'g']);
        $ingredient = Ingredient::factory()->create(['name' => 'Apple', 'base_unit_id' => $unit->id]);

        // Create an initial food log entry
        FoodLog::create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'quantity' => 100,
            'logged_at' => '2025-08-26 10:00:00',
            'notes' => 'Original Note',
        ]);

        $this->assertDatabaseCount('food_logs', 1);

        // TSV data with a duplicate and a new entry
        $tsvData = "2025-08-26\t10:00\tApple\tDuplicate Note\t100\n" . // Duplicate
                   "2025-08-26\t11:00\tApple\tNew Entry Note\t120";   // New entry

        $result = $this->tsvImporterService->importFoodLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']); // Only the new entry should be imported
        $this->assertEmpty($result['notFound']);
        $this->assertEmpty($result['invalidRows']);

        $this->assertDatabaseCount('food_logs', 2); // Original + 1 new entry

        // Assert the new entry was added
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 120,
            'notes' => 'New Entry Note',
            'logged_at' => '2025-08-26 11:00:00',
        ]);

        // Assert the duplicate was NOT added as a new entry (i.e., no third entry with the duplicate data)
        $this->assertDatabaseMissing('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 100,
            'notes' => 'Duplicate Note',
            'logged_at' => '2025-08-26 10:00:00',
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
    public function it_skips_duplicate_lift_logs_during_import()
    {
        $exercise = Exercise::where('user_id', $this->user->id)->where('title', 'Bench Press')->first();
        $this->assertNotNull($exercise);

        // Create an initial lift log entry with sets
        $initialLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Initial Log',
            'logged_at' => '2025-08-26 09:00',
        ]);
        $initialLiftLog->liftSets()->createMany([
            ['weight' => 100, 'reps' => 5, 'notes' => 'Initial Log'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Initial Log'],
            ['weight' => 100, 'reps' => 5, 'notes' => 'Initial Log'],
        ]);

        $this->assertDatabaseCount('lift_logs', 1);
        $this->assertDatabaseCount('lift_sets', 3);

        // TSV data with a duplicate and a new entry
        // New entry
        $tsvData = $initialLiftLog->logged_at->format("Y-m-d\tH:i") . "\tBench Press\t110\t4\t2\tNew Log";
        // Duplicate (should be skipped)
        $tsvData .= "\n" . $initialLiftLog->logged_at->format("Y-m-d\tH:i") . "\tBench Press\t100\t5\t3\tInitial Log";

        $result = $this->tsvImporterService->importLiftLogs($tsvData, '2025-08-26', $this->user->id);

        $this->assertEquals(1, $result['importedCount']); // Only the new entry should be imported
        $this->assertEmpty($result['notFound']);
        $this->assertEmpty($result['invalidRows']);

        $this->assertDatabaseCount('lift_logs', 2); // Original + 1 new entry
        $this->assertDatabaseCount('lift_sets', 3 + 2); // Original 3 sets + 2 new sets

        // Assert the new entry was added
        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'New Log',
            'logged_at' => '2025-08-26 09:00:00',
        ]);
        $newLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->where('logged_at', '2025-08-26 09:00:00')
            ->where('comments', 'New Log')
            ->first();
        $this->assertNotNull($newLiftLog);
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $newLiftLog->id,
            'weight' => 110,
            'reps' => 4,
            'notes' => 'New Log',
        ]);

        // Assert the duplicate was NOT added as a new entry
        $this->assertDatabaseMissing('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Duplicate Log',
            'logged_at' => '2025-08-26 09:00:00',
        ]);
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

    /** @test */
    public function it_imports_ingredients_and_updates_existing_ones()
    {
        $unit = Unit::factory()->create(['name' => 'gram', 'abbreviation' => 'g']);
        Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Ingredient A',
            'base_quantity' => 100,
            'base_unit_id' => $unit->id,
            'protein' => 10,
            'carbs' => 20,
            'fats' => 5,
            'cost_per_unit' => 1.50,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n";
        $tsvData = $header .
                   "Ingredient A\t150\tgram\t250\t10\t0\t25\t0\t0\t15\t0\t0\t0\t0\t2.00\n" .
                   "Ingredient B\t200\tgram\t400\t10\t0\t40\t0\t0\t20\t0\t0\t0\t0\t3.00";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEmpty($result['invalidRows']);
        
        // It's 5 because a User factory creates 5 ingredients by default
        $this->assertDatabaseCount('ingredients', 5 + 2);

        $this->assertDatabaseHas('ingredients', [
            'name' => 'Ingredient A',
            'base_quantity' => 150,
            'protein' => 15,
            'carbs' => 25,
            'fats' => 10,
            'cost_per_unit' => 2.00,
        ]);

        $this->assertDatabaseHas('ingredients', [
            'name' => 'Ingredient B',
        ]);
    }

    /** @test */
    public function it_returns_an_error_for_invalid_header()
    {
        $processor = new \App\Services\IngredientTsvProcessorService();
        $service = new TsvImporterService($processor);

        $tsvData = "Wrong Header\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Ingredient A\t150\tgram\t250\t10\t0\t25\t0\t0\t15\t0\t0\t0\t0\t2.00";

        $result = $service->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid TSV header', $result['error']);
        // It's 5 because a User factory creates 5 ingredients by default
        $this->assertDatabaseCount('ingredients', 5);
    }
}