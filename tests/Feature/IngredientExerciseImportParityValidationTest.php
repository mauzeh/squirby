<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Role;
use App\Services\TsvImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * This test validates that ingredient import behavior matches exercise import patterns exactly.
 * It compares the two import systems to ensure consistency in:
 * - Result structure and data types
 * - Error message patterns and formatting
 * - Success message structure and detail level
 * - Edge case handling
 * - Validation behavior
 */
class IngredientExerciseImportParityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $tsvImporterService;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Create admin user
        $this->admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);
        
        $this->tsvImporterService = app(TsvImporterService::class);
        
        // Create unit for ingredients
        $this->unit = Unit::factory()->create([
            'name' => 'grams',
            'abbreviation' => 'g',
            'conversion_factor' => 1.0
        ]);
        
        // Clear any existing ingredients that might be created by seeders
        Ingredient::query()->delete();
    }

    /** @test */
    public function import_result_structures_are_identical()
    {
        // Test exercise import result structure
        $exerciseTsvData = "Test Exercise\tTest Description\ttrue";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);

        // Test ingredient import result structure
        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" . "Test Ingredient\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Both should have identical result structure
        $expectedKeys = [
            'importedCount', 'updatedCount', 'skippedCount', 'invalidRows',
            'importedExercises', 'updatedExercises', 'skippedExercises', 'importMode'
        ];
        
        $ingredientKeys = [
            'importedCount', 'updatedCount', 'skippedCount', 'invalidRows',
            'importedIngredients', 'updatedIngredients', 'skippedIngredients', 'importMode'
        ];

        // Verify exercise result structure
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $exerciseResult, "Exercise result missing key: {$key}");
        }

        // Verify ingredient result structure (with ingredient-specific keys)
        foreach ($ingredientKeys as $key) {
            $this->assertArrayHasKey($key, $ingredientResult, "Ingredient result missing key: {$key}");
        }

        // Verify data types match
        $this->assertIsInt($exerciseResult['importedCount']);
        $this->assertIsInt($ingredientResult['importedCount']);
        $this->assertIsInt($exerciseResult['updatedCount']);
        $this->assertIsInt($ingredientResult['updatedCount']);
        $this->assertIsInt($exerciseResult['skippedCount']);
        $this->assertIsInt($ingredientResult['skippedCount']);
        $this->assertIsArray($exerciseResult['invalidRows']);
        $this->assertIsArray($ingredientResult['invalidRows']);
        $this->assertIsString($exerciseResult['importMode']);
        $this->assertIsString($ingredientResult['importMode']);

        // Verify import mode values
        $this->assertEquals('personal', $exerciseResult['importMode']);
        $this->assertEquals('personal', $ingredientResult['importMode']);

        // Verify counts match expected behavior
        $this->assertEquals(1, $exerciseResult['importedCount']);
        $this->assertEquals(1, $ingredientResult['importedCount']);
        $this->assertEquals(0, $exerciseResult['updatedCount']);
        $this->assertEquals(0, $ingredientResult['updatedCount']);
        $this->assertEquals(0, $exerciseResult['skippedCount']);
        $this->assertEquals(0, $ingredientResult['skippedCount']);
    }

    /** @test */
    public function success_message_structures_are_identical()
    {
        // Create existing items for update scenarios
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Update Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Update Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 10,
            'carbs' => 20,
            'fats' => 5,
            'cost_per_unit' => 2.00,
        ]);

        // Test exercise import with mixed scenarios
        $exerciseTsvData = "New Exercise\tNew description\ttrue\nUpdate Exercise\tUpdated description\ttrue\nUpdate Exercise\tUpdated description\ttrue";
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => $exerciseTsvData]);

        $exerciseMessage = session('success');
        session()->flush(); // Clear session for next test

        // Test ingredient import with mixed scenarios
        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" .
                           "New Ingredient\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00\n" .
                           "Update Ingredient\t150\tg\t150\t2\t0\t15\t0\t0\t8\t0\t0\t0\t0\t2.50\n" .
                           "Update Ingredient\t150\tg\t150\t2\t0\t15\t0\t0\t8\t0\t0\t0\t0\t2.50";
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => $ingredientTsvData]);

        $ingredientMessage = session('success');

        // Both messages should start with the same pattern
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $exerciseMessage);
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $ingredientMessage);

        // Both should have imported section with same HTML structure
        $this->assertStringContainsString('<p>Imported 1 new personal exercises:</p>', $exerciseMessage);
        $this->assertStringContainsString('<p>Imported 1 new ingredients:</p>', $ingredientMessage);
        $this->assertStringContainsString('<ul>', $exerciseMessage);
        $this->assertStringContainsString('<ul>', $ingredientMessage);
        $this->assertStringContainsString('<li>', $exerciseMessage);
        $this->assertStringContainsString('<li>', $ingredientMessage);
        $this->assertStringContainsString('</ul>', $exerciseMessage);
        $this->assertStringContainsString('</ul>', $ingredientMessage);

        // Both should have updated section with same HTML structure
        $this->assertStringContainsString('<p>Updated 1 existing personal exercises:</p>', $exerciseMessage);
        $this->assertStringContainsString('<p>Updated 1 existing ingredients:</p>', $ingredientMessage);

        // Both should have skipped section with same HTML structure
        $this->assertStringContainsString('<p>Skipped 1 exercises:</p>', $exerciseMessage);
        $this->assertStringContainsString('<p>Skipped 1 ingredients:</p>', $ingredientMessage);

        // Both should show change details in same format
        $this->assertStringContainsString('description: \'Old description\' → \'Updated description\'', $exerciseMessage);
        $this->assertStringContainsString('protein: \'10\' → \'8\'', $ingredientMessage);
        $this->assertStringContainsString('carbs: \'20\' → \'15\'', $ingredientMessage);

        // Both should show skip reasons in same format
        $this->assertStringContainsString('already exists with same data', $exerciseMessage);
        $this->assertStringContainsString('already exists with same data', $ingredientMessage);
    }

    /** @test */
    public function error_message_patterns_are_identical()
    {
        // Test empty data validation
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => '']);
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => '']);

        // Both should have identical validation error structure
        $exerciseResponse->assertSessionHasErrors(['tsv_data']);
        $ingredientResponse->assertSessionHasErrors(['tsv_data']);

        // Test missing tsv_data field
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), []);
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), []);

        $exerciseResponse->assertSessionHasErrors(['tsv_data']);
        $ingredientResponse->assertSessionHasErrors(['tsv_data']);

        // Test service exception handling patterns
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => "Valid Exercise\tDescription\ttrue\n\t\t\t"]);

        // Both should handle exceptions gracefully with either success or error
        $this->assertTrue(
            session()->has('success') || session()->has('error'),
            'Exercise import should have either success or error message'
        );
        
        session()->flush();
        
        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => $ingredientHeader . "\nValid\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00\n\t\t\t\t\t"]);

        $this->assertTrue(
            session()->has('success') || session()->has('error'),
            'Ingredient import should have either success or error message'
        );
    }

    /** @test */
    public function case_insensitive_matching_behavior_is_identical()
    {
        // Create existing items with mixed case
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Push Ups',
            'description' => 'Original description',
            'is_bodyweight' => false,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 0,
            'fats' => 3,
            'cost_per_unit' => 5.00,
        ]);

        // Test case-insensitive updates
        $exerciseTsvData = "PUSH UPS\tUpdated description\ttrue";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" . "CHICKEN BREAST\t150\tg\t165\t4\t75\t0\t0\t0\t30\t15\t256\t0\t1\t6.00";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Both should update existing items (case-insensitive match)
        $this->assertEquals(0, $exerciseResult['importedCount']);
        $this->assertEquals(1, $exerciseResult['updatedCount']);
        $this->assertEquals(0, $ingredientResult['importedCount']);
        $this->assertEquals(1, $ingredientResult['updatedCount']);

        // Verify only one item exists for each (no duplicates created)
        $this->assertEquals(1, Exercise::where('user_id', $this->user->id)->whereRaw('LOWER(title) = ?', ['push ups'])->count());
        $this->assertEquals(1, Ingredient::where('user_id', $this->user->id)->whereRaw('LOWER(name) = ?', ['chicken breast'])->count());

        // Verify items were actually updated
        $exercise = Exercise::where('user_id', $this->user->id)->where('title', 'Push Ups')->first();
        $this->assertEquals('Updated description', $exercise->description);
        $this->assertTrue($exercise->is_bodyweight);

        $ingredient = Ingredient::where('user_id', $this->user->id)->where('name', 'Chicken Breast')->first();
        $this->assertEquals(150, $ingredient->base_quantity);
        $this->assertEquals(30, $ingredient->protein);
        $this->assertEquals(6.00, $ingredient->cost_per_unit);
    }

    /** @test */
    public function skip_behavior_for_identical_data_is_consistent()
    {
        // Create items with specific data
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Identical Exercise',
            'description' => 'Same description',
            'is_bodyweight' => true,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Identical Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 10,
            'fats' => 5,
            'sodium' => 50,
            'fiber' => 2,
            'calcium' => 100,
            'potassium' => 200,
            'iron' => 1,
            'caffeine' => 0,
            'added_sugars' => 3,
            'cost_per_unit' => 4.99,
        ]);

        // Import identical data
        $exerciseTsvData = "Identical Exercise\tSame description\ttrue";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" . "Identical Ingredient\t100\tg\t165\t5\t50\t10\t2\t3\t25\t100\t200\t0\t1\t4.99";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Both should skip items with identical data
        $this->assertEquals(0, $exerciseResult['importedCount']);
        $this->assertEquals(0, $exerciseResult['updatedCount']);
        $this->assertEquals(1, $exerciseResult['skippedCount']);
        $this->assertEquals(0, $ingredientResult['importedCount']);
        $this->assertEquals(0, $ingredientResult['updatedCount']);
        $this->assertEquals(1, $ingredientResult['skippedCount']);

        // Both should have consistent skip reason format
        $exerciseSkip = $exerciseResult['skippedExercises'][0];
        $ingredientSkip = $ingredientResult['skippedIngredients'][0];
        
        $this->assertStringContainsString('already exists with same data', $exerciseSkip['reason']);
        $this->assertStringContainsString('already exists with same data', $ingredientSkip['reason']);
        $this->assertEquals('Identical Exercise', $exerciseSkip['title']);
        $this->assertEquals('Identical Ingredient', $ingredientSkip['name']);
    }

    /** @test */
    public function invalid_row_handling_is_consistent()
    {
        // Test with invalid rows mixed with valid data
        $exerciseTsvData = "Valid Exercise\tValid Description\ttrue\nInvalid\nAnother Valid\tAnother Description\tfalse\n\t\t";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" .
                           "Valid Ingredient\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00\n" .
                           "Invalid\tRow\tWith\tToo\tFew\tColumns\n" .
                           "Another Valid\t150\tg\t150\t2\t0\t15\t0\t0\t8\t0\t0\t0\t0\t2.00\n" .
                           "\t\t\t\t\t";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Both should import valid rows and track invalid ones
        $this->assertEquals(2, $exerciseResult['importedCount']);
        $this->assertEquals(2, $ingredientResult['importedCount']);
        $this->assertCount(1, $exerciseResult['invalidRows']);
        $this->assertCount(1, $ingredientResult['invalidRows']);

        // Both should continue processing despite invalid rows
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Valid Exercise',
        ]);
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Another Valid',
        ]);
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Valid Ingredient',
        ]);
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Another Valid',
        ]);
    }

    /** @test */
    public function change_tracking_detail_level_is_consistent()
    {
        // Create existing items with multiple fields to change
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Change Tracking Exercise',
            'description' => 'Original description',
            'is_bodyweight' => false,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Change Tracking Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 15,
            'fats' => 5,
            'sodium' => 100,
            'fiber' => 2,
            'calcium' => 50,
            'potassium' => 200,
            'iron' => 1,
            'caffeine' => 0,
            'added_sugars' => 3,
            'cost_per_unit' => 2.50,
        ]);

        // Update with multiple field changes
        $exerciseTsvData = "Change Tracking Exercise\tUpdated description\ttrue";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" . "Change Tracking Ingredient\t150\tg\t200\t8\t150\t20\t4\t5\t25\t80\t300\t10\t2\t3.50";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Both should track changes with from/to structure
        $exerciseChanges = $exerciseResult['updatedExercises'][0]['changes'];
        $ingredientChanges = $ingredientResult['updatedIngredients'][0]['changes'];

        // Exercise changes should have from/to structure
        $this->assertArrayHasKey('description', $exerciseChanges);
        $this->assertArrayHasKey('is_bodyweight', $exerciseChanges);
        $this->assertEquals('Original description', $exerciseChanges['description']['from']);
        $this->assertEquals('Updated description', $exerciseChanges['description']['to']);
        $this->assertEquals(false, $exerciseChanges['is_bodyweight']['from']);
        $this->assertEquals(true, $exerciseChanges['is_bodyweight']['to']);

        // Ingredient changes should have same from/to structure
        $expectedIngredientChanges = [
            'base_quantity', 'protein', 'carbs', 'fats', 'sodium', 'fiber',
            'calcium', 'potassium', 'iron', 'caffeine', 'added_sugars', 'cost_per_unit'
        ];
        
        foreach ($expectedIngredientChanges as $field) {
            $this->assertArrayHasKey($field, $ingredientChanges, "Missing change tracking for field: {$field}");
            $this->assertArrayHasKey('from', $ingredientChanges[$field], "Missing 'from' value for field: {$field}");
            $this->assertArrayHasKey('to', $ingredientChanges[$field], "Missing 'to' value for field: {$field}");
        }

        // Verify specific change values
        $this->assertEquals(100, $ingredientChanges['base_quantity']['from']);
        $this->assertEquals(150, $ingredientChanges['base_quantity']['to']);
        $this->assertEquals(20, $ingredientChanges['protein']['from']);
        $this->assertEquals(25, $ingredientChanges['protein']['to']);
    }

    /** @test */
    public function personal_only_model_is_maintained_for_ingredients()
    {
        // Ingredients should always be personal (user_id = current user)
        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\n" . "Personal Only Ingredient\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00";
        $ingredientResult = $this->tsvImporterService->importIngredients($ingredientTsvData, $this->user->id);

        // Should always be personal mode
        $this->assertEquals('personal', $ingredientResult['importMode']);

        // Should create ingredient with user_id
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Personal Only Ingredient',
        ]);

        // Should never create global ingredients (user_id = null)
        $this->assertDatabaseMissing('ingredients', [
            'user_id' => null,
            'name' => 'Personal Only Ingredient',
        ]);

        // Compare with exercise which can be both personal and global
        $exerciseTsvData = "Personal Exercise\tPersonal Description\ttrue";
        $exerciseResult = $this->tsvImporterService->importExercises($exerciseTsvData, $this->user->id);
        $this->assertEquals('personal', $exerciseResult['importMode']);

        // Admin can create global exercises
        $globalExerciseTsvData = "Global Exercise\tGlobal Description\ttrue";
        $globalExerciseResult = $this->tsvImporterService->importExercises($globalExerciseTsvData, $this->admin->id, true);
        $this->assertEquals('global', $globalExerciseResult['importMode']);

        // But ingredients should never have global mode option
        // (This is tested by the fact that importIngredients doesn't accept a global parameter)
    }

    /** @test */
    public function web_interface_validation_patterns_are_identical()
    {
        // Test validation error responses through web interface
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => '']);
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => '']);

        // Both should redirect back with validation errors
        $exerciseResponse->assertRedirect();
        $ingredientResponse->assertRedirect();
        $exerciseResponse->assertSessionHasErrors(['tsv_data']);
        $ingredientResponse->assertSessionHasErrors(['tsv_data']);

        // Test successful import redirects
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => 'Test Exercise\tDescription\ttrue']);
        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => $ingredientHeader . "\nTest Ingredient\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00"]);

        // Both should redirect to their respective index pages
        $exerciseResponse->assertRedirect(route('exercises.index'));
        $ingredientResponse->assertRedirect(route('ingredients.index'));
        $exerciseResponse->assertSessionHas('success');
        $ingredientResponse->assertSessionHas('success');
    }

    /** @test */
    public function html_escaping_in_success_messages_is_consistent()
    {
        // Test with potentially problematic characters that need escaping
        $exerciseTsvData = "Exercise <script>alert('xss')</script> with 'quotes' & ampersands\tDescription\ttrue";
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => $exerciseTsvData]);

        $exerciseMessage = session('success');
        session()->flush();

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientTsvData = $ingredientHeader . "\nIngredient <script>alert('xss')</script> with 'quotes' & ampersands\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00";
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => $ingredientTsvData]);

        $ingredientMessage = session('success');

        // Both should properly escape HTML in success messages
        $this->assertStringContainsString('&lt;script&gt;', $exerciseMessage);
        $this->assertStringContainsString('&lt;script&gt;', $ingredientMessage);
        $this->assertStringNotContainsString('<script>', $exerciseMessage);
        $this->assertStringNotContainsString('<script>', $ingredientMessage);

        // Both should escape quotes and ampersands consistently
        $this->assertStringContainsString('&#039;', $exerciseMessage);
        $this->assertStringContainsString('&amp;', $exerciseMessage);
    }

    /** @test */
    public function no_data_imported_message_is_identical()
    {
        // Create existing items that match import data exactly
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Same Exercise',
            'description' => 'Same description',
            'is_bodyweight' => true,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Same Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 10,
            'fats' => 5,
            'cost_per_unit' => 2.50,
        ]);

        // Import identical data
        $exerciseResponse = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), ['tsv_data' => 'Same Exercise\tSame description\ttrue']);

        $exerciseMessage = session('success');
        session()->flush();

        $ingredientHeader = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)";
        $ingredientResponse = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), ['tsv_data' => $ingredientHeader . "\nSame Ingredient\t100\tg\t120\t5\t0\t10\t0\t0\t25\t0\t0\t0\t0\t2.50"]);

        $ingredientMessage = session('success');

        // Both should have identical "no data imported" message
        $expectedMessage = 'No new data was imported or updated - all entries already exist with the same data.';
        $this->assertStringContainsString($expectedMessage, $exerciseMessage);
        $this->assertStringContainsString($expectedMessage, $ingredientMessage);
    }
}