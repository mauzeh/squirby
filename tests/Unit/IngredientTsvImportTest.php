<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TsvImporterService;
use App\Services\IngredientTsvProcessorService;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientTsvImportTest extends TestCase
{
    use RefreshDatabase;

    protected $tsvImporterService;
    protected $user;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsvImporterService = new TsvImporterService(new IngredientTsvProcessorService());
        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->create(['name' => 'gram', 'abbreviation' => 'g']);
    }

    /** @test */
    public function it_imports_new_ingredients_with_detailed_result_tracking()
    {
        $initialCount = Ingredient::where('user_id', $this->user->id)->count();
        
        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Apple\t100\tg\t52\t0.2\t1\t14\t2.4\t10\t0.3\t6\t107\t0\t0.12\t1.50\n" .
                   "Banana\t118\tg\t89\t0.3\t1\t23\t2.6\t12\t1.1\t5\t358\t0\t0.26\t0.75";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertEquals('personal', $result['importMode']);
        $this->assertCount(2, $result['importedIngredients']);
        $this->assertEmpty($result['updatedIngredients']);
        $this->assertEmpty($result['skippedIngredients']);

        $ingredients = Ingredient::where('user_id', $this->user->id)->get();
        $this->assertCount($initialCount + 2, $ingredients);

        $apple = $ingredients->where('name', 'Apple')->first();
        $this->assertNotNull($apple);
        $this->assertEquals(100, $apple->base_quantity);
        $this->assertEquals(0.3, $apple->protein);
        $this->assertEquals(14, $apple->carbs);
        $this->assertEquals(0.2, $apple->fats);
        $this->assertEquals(1.50, $apple->cost_per_unit);
        $this->assertEquals($this->user->id, $apple->user_id);

        $banana = $ingredients->where('name', 'Banana')->first();
        $this->assertNotNull($banana);
        $this->assertEquals(118, $banana->base_quantity);
        $this->assertEquals(1.1, $banana->protein);
        $this->assertEquals(23, $banana->carbs);
        $this->assertEquals(0.3, $banana->fats);
        $this->assertEquals(0.75, $banana->cost_per_unit);
        $this->assertEquals($this->user->id, $banana->user_id);

        // Verify detailed ingredient lists
        $importedIngredient = $result['importedIngredients'][0];
        $this->assertEquals('Apple', $importedIngredient['name']);
        $this->assertEquals(100, $importedIngredient['base_quantity']);
        $this->assertEquals('g', $importedIngredient['unit_abbreviation']);
    }

    /** @test */
    public function it_updates_existing_ingredients_with_change_detection()
    {
        // Create an existing ingredient
        $existingIngredient = Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Orange',
            'base_quantity' => 150,
            'base_unit_id' => $this->unit->id,
            'protein' => 1.0,
            'carbs' => 12,
            'fats' => 0.2,
            'sodium' => 0,
            'fiber' => 2.4,
            'calcium' => 40,
            'potassium' => 181,
            'cost_per_unit' => 1.25,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Orange\t200\tg\t62\t0.3\t2\t15\t3.0\t11\t1.2\t50\t200\t0\t0.15\t1.75";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertCount(1, $result['updatedIngredients']);

        $existingIngredient->refresh();
        $this->assertEquals(200, $existingIngredient->base_quantity);
        $this->assertEquals(1.2, $existingIngredient->protein);
        $this->assertEquals(15, $existingIngredient->carbs);
        $this->assertEquals(0.3, $existingIngredient->fats);
        $this->assertEquals(1.75, $existingIngredient->cost_per_unit);

        // Verify change tracking
        $updatedIngredient = $result['updatedIngredients'][0];
        $this->assertEquals('Orange', $updatedIngredient['name']);
        $this->assertArrayHasKey('changes', $updatedIngredient);
        $this->assertArrayHasKey('base_quantity', $updatedIngredient['changes']);
        $this->assertArrayHasKey('protein', $updatedIngredient['changes']);
        $this->assertArrayHasKey('carbs', $updatedIngredient['changes']);
        $this->assertArrayHasKey('fats', $updatedIngredient['changes']);
        $this->assertArrayHasKey('cost_per_unit', $updatedIngredient['changes']);
        
        $this->assertEquals(150, $updatedIngredient['changes']['base_quantity']['from']);
        $this->assertEquals(200, $updatedIngredient['changes']['base_quantity']['to']);
        $this->assertEquals(1.0, $updatedIngredient['changes']['protein']['from']);
        $this->assertEquals(1.2, $updatedIngredient['changes']['protein']['to']);
    }

    /** @test */
    public function it_skips_ingredients_with_identical_data()
    {
        // Create an existing ingredient with same data
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Grape',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 0.7,
            'carbs' => 16,
            'fats' => 0.2,
            'sodium' => 2,
            'fiber' => 0.9,
            'calcium' => 10,
            'potassium' => 191,
            'iron' => 0.36,
            'caffeine' => 0,
            'added_sugars' => 15,
            'cost_per_unit' => 2.50,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Grape\t100\tg\t69\t0.2\t2\t16\t0.9\t15\t0.7\t10\t191\t0\t0.36\t2.50";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(1, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertCount(1, $result['skippedIngredients']);

        $skippedIngredient = $result['skippedIngredients'][0];
        $this->assertEquals('Grape', $skippedIngredient['name']);
        $this->assertStringContainsString('already exists with same data', $skippedIngredient['reason']);

        // Verify no changes were made
        $ingredients = Ingredient::where('user_id', $this->user->id)->where('name', 'Grape')->get();
        $this->assertCount(1, $ingredients);
    }

    /** @test */
    public function it_handles_case_insensitive_ingredient_name_matching()
    {
        // Create existing ingredient with mixed case
        $existingIngredient = Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Strawberry',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 0.7,
            'carbs' => 8,
            'fats' => 0.3,
            'cost_per_unit' => 3.00,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "STRAWBERRY\t150\tg\t32\t0.4\t1\t10\t2.0\t5\t0.8\t16\t153\t0\t0.41\t3.50";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']); // Should update existing ingredient
        $this->assertEquals(0, $result['skippedCount']);
        
        $ingredients = Ingredient::where('user_id', $this->user->id)->whereRaw('LOWER(name) = ?', ['strawberry'])->get();
        $this->assertCount(1, $ingredients); // Should still be only one ingredient
        
        $existingIngredient->refresh();
        $this->assertEquals(150, $existingIngredient->base_quantity); // Updated with TSV row
        $this->assertEquals(0.8, $existingIngredient->protein);
        $this->assertEquals(10, $existingIngredient->carbs);
        $this->assertEquals(3.50, $existingIngredient->cost_per_unit);
    }

    /** @test */
    public function it_handles_invalid_row_handling_and_reporting()
    {
        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Apple\t100\tg\t52\t0.2\t1\t14\t2.4\t10\t0.3\t6\t107\t0\t0.12\t1.50\n" .
                   "Invalid\tRow\tWith\tToo\tFew\tColumns\n" .
                   "Banana\t118\tg\t89\t0.3\t1\t23\t2.6\t12\t1.1\t5\t358\t0\t0.26\t0.75\n" .
                   "Another\tInvalid\tRow\tMissing\tData";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']); // Apple and Banana
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertCount(2, $result['invalidRows']);
        $this->assertCount(2, $result['importedIngredients']);

        // Verify valid ingredients were imported
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Apple',
            'base_quantity' => 100,
        ]);
        
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Banana',
            'base_quantity' => 118,
        ]);

        // Verify invalid rows were captured
        $this->assertStringContainsString('Invalid', $result['invalidRows'][0]);
        $this->assertStringContainsString('Another', $result['invalidRows'][1]);
    }

    /** @test */
    public function it_handles_invalid_tsv_header()
    {
        $invalidHeader = "Wrong\tHeader\tFormat\tThat\tDoesnt\tMatch\tExpected\tColumns\tAt\tAll\tReally\tBad\tHeader\tVery\tWrong";
        $tsvData = $invalidHeader . "\n" .
                   "Apple\t100\tg\t52\t0.2\t1\t14\t2.4\t10\t0.3\t6\t107\t0\t0.12\t1.50";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEmpty($result['invalidRows']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid TSV header', $result['error']);
        
        // No ingredients should be created
        $this->assertDatabaseMissing('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Apple',
        ]);
    }

    /** @test */
    public function it_handles_unknown_unit_abbreviation()
    {
        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Apple\t100\tunknown_unit\t52\t0.2\t1\t14\t2.4\t10\t0.3\t6\t107\t0\t0.12\t1.50";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertCount(1, $result['invalidRows']);
        
        // Verify the error message contains unit information
        $this->assertStringContainsString('Unit not found', $result['invalidRows'][0]);
        $this->assertStringContainsString('unknown_unit', $result['invalidRows'][0]);
    }

    /** @test */
    public function it_processes_mixed_import_scenarios()
    {
        // Create existing ingredients
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Existing Apple',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 0.3,
            'carbs' => 14,
            'fats' => 0.2,
            'cost_per_unit' => 1.00,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Same Data Ingredient',
            'base_quantity' => 150,
            'base_unit_id' => $this->unit->id,
            'protein' => 1.0,
            'carbs' => 20,
            'fats' => 0.5,
            'sodium' => 3,
            'fiber' => 2.0,
            'calcium' => 25,
            'potassium' => 180,
            'iron' => 0.3,
            'caffeine' => 0,
            'added_sugars' => 18,
            'cost_per_unit' => 2.00,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "New Ingredient\t200\tg\t100\t1.0\t5\t25\t3.0\t15\t2.0\t30\t200\t0\t0.5\t3.00\n" .
                   "Existing Apple\t120\tg\t60\t0.4\t2\t16\t2.5\t12\t0.4\t8\t120\t0\t0.15\t1.25\n" .
                   "Same Data Ingredient\t150\tg\t85\t0.5\t3\t20\t2.0\t18\t1.0\t25\t180\t0\t0.3\t2.00\n" .
                   "Invalid\tRow\tWith\tToo\tFew\tColumns\n" .
                   "Another New Ingredient\t300\tg\t150\t2.0\t10\t30\t4.0\t20\t3.0\t40\t250\t0\t0.8\t4.00";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(2, $result['importedCount']); // New Ingredient + Another New Ingredient
        $this->assertEquals(1, $result['updatedCount']); // Existing Apple
        $this->assertEquals(1, $result['skippedCount']); // Same Data Ingredient
        $this->assertCount(1, $result['invalidRows']); // Invalid row

        $this->assertCount(2, $result['importedIngredients']);
        $this->assertCount(1, $result['updatedIngredients']);
        $this->assertCount(1, $result['skippedIngredients']);

        // Verify the results
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'New Ingredient',
            'base_quantity' => 200,
        ]);

        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Another New Ingredient',
            'base_quantity' => 300,
        ]);

        // Verify existing apple was updated
        $existingApple = Ingredient::where('user_id', $this->user->id)->where('name', 'Existing Apple')->first();
        $this->assertEquals(120, $existingApple->base_quantity);
        $this->assertEquals(1.25, $existingApple->cost_per_unit);
    }

    /** @test */
    public function it_only_processes_personal_ingredients_for_user()
    {
        // Create another user with their own ingredient
        $otherUser = User::factory()->create();
        Ingredient::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Apple',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 0.3,
            'carbs' => 14,
            'fats' => 0.2,
            'cost_per_unit' => 1.00,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Other User Apple\t150\tg\t60\t0.4\t2\t16\t2.5\t12\t0.4\t8\t120\t0\t0.15\t1.25";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        // Should create a new ingredient for this user, not update the other user's ingredient
        $this->assertEquals(1, $result['importedCount']);
        $this->assertEquals(0, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);
        $this->assertEquals('personal', $result['importMode']);

        // Verify both ingredients exist separately
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $otherUser->id,
            'name' => 'Other User Apple',
            'base_quantity' => 100, // Unchanged
        ]);

        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Other User Apple',
            'base_quantity' => 150, // New ingredient for this user
        ]);
    }

    /** @test */
    public function it_handles_nutritional_field_changes_correctly()
    {
        // Create existing ingredient with specific nutritional values
        $existingIngredient = Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Nutritional Test',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 5.0,
            'carbs' => 20.0,
            'fats' => 2.0,
            'sodium' => 100.0,
            'fiber' => 3.0,
            'calcium' => 50.0,
            'potassium' => 200.0,
            'iron' => 1.0,
            'caffeine' => 0.0,
            'added_sugars' => 5.0,
            'cost_per_unit' => 2.50,
        ]);

        $header = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $tsvData = $header . "\n" .
                   "Nutritional Test\t120\tg\t150\t3.0\t150\t25.0\t4.0\t8.0\t6.0\t60.0\t250.0\t10.0\t1.5\t3.00";

        $result = $this->tsvImporterService->importIngredients($tsvData, $this->user->id);

        $this->assertEquals(0, $result['importedCount']);
        $this->assertEquals(1, $result['updatedCount']);
        $this->assertEquals(0, $result['skippedCount']);

        $updatedIngredient = $result['updatedIngredients'][0];
        $changes = $updatedIngredient['changes'];

        // Verify all changed fields are tracked
        $expectedChanges = [
            'base_quantity' => ['from' => 100.0, 'to' => 120.0],
            'protein' => ['from' => 5.0, 'to' => 6.0],
            'carbs' => ['from' => 20.0, 'to' => 25.0],
            'fats' => ['from' => 2.0, 'to' => 3.0],
            'sodium' => ['from' => 100.0, 'to' => 150.0],
            'fiber' => ['from' => 3.0, 'to' => 4.0],
            'calcium' => ['from' => 50.0, 'to' => 60.0],
            'potassium' => ['from' => 200.0, 'to' => 250.0],
            'iron' => ['from' => 1.0, 'to' => 1.5],
            'caffeine' => ['from' => 0.0, 'to' => 10.0],
            'added_sugars' => ['from' => 5.0, 'to' => 8.0],
            'cost_per_unit' => ['from' => 2.50, 'to' => 3.00],
        ];

        foreach ($expectedChanges as $field => $expectedChange) {
            $this->assertArrayHasKey($field, $changes, "Field {$field} should be tracked as changed");
            $this->assertEquals($expectedChange['from'], $changes[$field]['from'], "Field {$field} 'from' value mismatch");
            $this->assertEquals($expectedChange['to'], $changes[$field]['to'], "Field {$field} 'to' value mismatch");
        }
    }
}