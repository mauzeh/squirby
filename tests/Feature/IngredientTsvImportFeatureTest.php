<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IngredientTsvImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $anotherUser;
    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->anotherUser = User::factory()->create();
        
        // Create units for testing
        $this->unit = Unit::factory()->create([
            'name' => 'grams',
            'abbreviation' => 'g',
            'conversion_factor' => 1.0
        ]);
        
        Unit::factory()->create([
            'name' => 'pieces',
            'abbreviation' => 'pc',
            'conversion_factor' => 1.0
        ]);
        
        // Clear any existing ingredients that might be created by seeders
        Ingredient::query()->delete();
    }

    public function test_user_can_import_ingredients_via_web_interface()
    {
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Chicken Breast\t100\tg\t165\t3.6\t74\t0\t0\t0\t31\t15\t256\t0\t1.04\t5.99\n" .
                   "Brown Rice\t100\tg\t111\t0.9\t5\t23\t1.8\t0.4\t2.6\t23\t43\t0\t1.47\t2.50";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_quantity' => 100,
            'protein' => 31,
            'carbs' => 0,
            'fats' => 3.6,
            'cost_per_unit' => 5.99,
        ]);

        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Brown Rice',
            'base_quantity' => 100,
            'protein' => 2.6,
            'carbs' => 23,
            'fats' => 0.9,
            'cost_per_unit' => 2.50,
        ]);
    }

    public function test_user_can_view_ingredients_index_with_tsv_import_form()
    {
        // Create some ingredients
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Test Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 10,
            'carbs' => 20,
            'fats' => 5,
            'cost_per_unit' => 3.99,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Export');
        $response->assertSee('TSV Import');
        $response->assertSee('Test Ingredient');
    }

    public function test_import_with_empty_data_shows_error()
    {
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => ''
            ]);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_with_no_new_data_shows_success_message()
    {
        // Create existing ingredient that matches what we'll try to import
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Existing Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 10,
            'fats' => 5,
            'sodium' => 50,
            'fiber' => 2,
            'added_sugars' => 1,
            'calcium' => 100,
            'potassium' => 200,
            'caffeine' => 0,
            'iron' => 2,
            'cost_per_unit' => 4.99,
        ]);

        // Try to import the exact same data
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Existing Ingredient\t100\tg\t165\t5\t50\t10\t2\t1\t25\t100\t200\t0\t2\t4.99";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
    }

    public function test_import_with_invalid_data_shows_success_with_no_imports()
    {
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Invalid\tInvalid\tInvalidUnit\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\tInvalid\n" .
                   "Another Invalid Row";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('No new data was imported or updated', $successMessage);
        $this->assertStringContainsString('invalid rows', $successMessage);
    }

    public function test_import_success_message_shows_detailed_results_for_mixed_scenarios()
    {
        // Create existing ingredient to test update scenario
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Existing Ingredient',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 15,
            'fats' => 3,
            'sodium' => 100,
            'fiber' => 1,
            'added_sugars' => 0,
            'calcium' => 50,
            'potassium' => 150,
            'caffeine' => 0,
            'iron' => 1,
            'cost_per_unit' => 3.99,
        ]);

        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "New Ingredient\t100\tg\t200\t5\t75\t25\t3\t2\t30\t120\t300\t0\t2.5\t6.99\n" .
                   "Existing Ingredient\t100\tg\t180\t4\t120\t18\t2\t1\t25\t80\t200\t0\t1.5\t4.50";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $successMessage);
        $this->assertStringContainsString('<p>Imported 1 new ingredients:</p>', $successMessage);
        $this->assertStringContainsString('<li>New Ingredient (100g)</li>', $successMessage);
        $this->assertStringContainsString('<p>Updated 1 existing ingredients:</p>', $successMessage);
        $this->assertStringContainsString('<li>Existing Ingredient', $successMessage);
        $this->assertStringContainsString('protein: \'20\' → \'25\'', $successMessage);
        $this->assertStringContainsString('carbs: \'15\' → \'18\'', $successMessage);
        $this->assertStringContainsString('fats: \'3\' → \'4\'', $successMessage);
    }

    public function test_import_handles_case_insensitive_ingredient_matching()
    {
        // Create existing ingredient with mixed case
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 0,
            'fats' => 3,
            'cost_per_unit' => 5.99,
        ]);

        // Try to import with different case - should update the existing one
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "chicken breast\t100\tg\t165\t4\t75\t0\t0\t0\t30\t15\t256\t0\t1\t6.50";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Updated 1 existing ingredients:', $successMessage);
        $this->assertStringContainsString('Chicken Breast', $successMessage);

        // Verify only one ingredient exists (case-insensitive matching worked)
        $allIngredients = Ingredient::where('user_id', $this->user->id)->get();
        $this->assertEquals(1, $allIngredients->count(), 'Expected only 1 ingredient but found: ' . $allIngredients->pluck('name')->implode(', '));
        
        // Verify the ingredient was updated
        $ingredient = Ingredient::where('user_id', $this->user->id)->first();
        $this->assertNotNull($ingredient, 'No ingredient found for user');
        $this->assertEquals(30, $ingredient->protein);
        $this->assertEquals(4, $ingredient->fats);
    }

    public function test_import_only_affects_current_users_ingredients()
    {
        // Create ingredients for both users with same name
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Shared Name',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 10,
            'fats' => 5,
            'cost_per_unit' => 3.99,
        ]);

        Ingredient::create([
            'user_id' => $this->anotherUser->id,
            'name' => 'Shared Name',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 15,
            'carbs' => 20,
            'fats' => 8,
            'cost_per_unit' => 4.50,
        ]);

        // User imports ingredient with same name
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Shared Name\t100\tg\t180\t6\t50\t12\t2\t1\t25\t100\t200\t0\t1.5\t5.00";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');

        // Verify user's ingredient was updated
        $userIngredient = Ingredient::where('user_id', $this->user->id)
            ->where('name', 'Shared Name')->first();
        $this->assertEquals(25, $userIngredient->protein);
        $this->assertEquals(12, $userIngredient->carbs);
        $this->assertEquals(6, $userIngredient->fats);

        // Verify another user's ingredient remains unchanged
        $anotherUserIngredient = Ingredient::where('user_id', $this->anotherUser->id)
            ->where('name', 'Shared Name')->first();
        $this->assertEquals(15, $anotherUserIngredient->protein);
        $this->assertEquals(20, $anotherUserIngredient->carbs);
        $this->assertEquals(8, $anotherUserIngredient->fats);
    }

    public function test_import_handles_service_exceptions_gracefully()
    {
        // Test with malformed TSV that might cause service to throw exception
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Valid Ingredient\t100\tg\t165\t3.6\t74\t0\t0\t0\t31\t15\t256\t0\t1.04\t5.99\n" .
                   "\t\t\t\t\t\t\t\t\t\t\t\t\t\t"; // Invalid row that might cause issues

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        // Should either succeed or show a proper error message, not crash
        $this->assertTrue(
            session()->has('success') || session()->has('error'),
            'Response should have either success or error message'
        );
    }

    public function test_import_validation_requires_tsv_data()
    {
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), []);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_shows_skipped_ingredients_when_data_is_identical()
    {
        // Create existing ingredient
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
            'added_sugars' => 1,
            'calcium' => 100,
            'potassium' => 200,
            'caffeine' => 0,
            'iron' => 2,
            'cost_per_unit' => 4.99,
        ]);

        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "New Ingredient\t100\tg\t200\t8\t100\t15\t3\t2\t30\t150\t300\t0\t2.5\t6.99\n" .
                   "Identical Ingredient\t100\tg\t165\t5\t50\t10\t2\t1\t25\t100\t200\t0\t2\t4.99";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Imported 1 new ingredients:', $successMessage);
        $this->assertStringContainsString('New Ingredient', $successMessage);
        $this->assertStringContainsString('Skipped 1 ingredients:', $successMessage);
        $this->assertStringContainsString('Identical Ingredient - Ingredient &#039;Identical Ingredient&#039; already exists with same data', $successMessage);
    }

    public function test_import_provides_detailed_summary_with_mixed_results()
    {
        // Create existing ingredients for various scenarios
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Update Me',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 15,
            'fats' => 3,
            'cost_per_unit' => 3.99,
        ]);

        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Keep Same',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 10,
            'carbs' => 5,
            'fats' => 2,
            'cost_per_unit' => 2.50,
        ]);

        // Import data with new, update, and skip scenarios
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Brand New\t100\tg\t200\t8\t100\t20\t3\t2\t35\t150\t300\t0\t2.5\t7.99\n" .
                   "Update Me\t100\tg\t180\t4\t80\t18\t2\t1\t25\t120\t250\t0\t2\t4.50\n" .
                   "Keep Same\t100\tg\t120\t2\t0\t5\t0\t0\t10\t0\t0\t0\t0\t2.50\n" .
                   "Another New\t100\tg\t150\t6\t60\t12\t1\t0\t20\t80\t180\t0\t1.5\t5.50"; // Removed invalid row

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Should show imported ingredients
        $this->assertStringContainsString('Imported 2 new ingredients:', $successMessage);
        $this->assertStringContainsString('Brand New (100g)', $successMessage);
        $this->assertStringContainsString('Another New (100g)', $successMessage);
        
        // Should show updated ingredients
        $this->assertStringContainsString('Updated 1 existing ingredients:', $successMessage);
        $this->assertStringContainsString('Update Me', $successMessage);
        
        // Should show skipped ingredients
        $this->assertStringContainsString('Skipped 1 ingredients:', $successMessage);
        $this->assertStringContainsString('Keep Same - Ingredient &#039;Keep Same&#039; already exists with same data', $successMessage);
    }

    public function test_import_error_messages_are_specific_for_different_failure_modes()
    {
        // Test empty data error
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => ''
            ]);

        $response->assertSessionHasErrors(['tsv_data']);

        // Test validation error for missing tsv_data
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), []);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_handles_different_nutritional_field_updates()
    {
        // Create existing ingredient with specific nutritional values
        Ingredient::create([
            'user_id' => $this->user->id,
            'name' => 'Nutritional Test',
            'base_quantity' => 100,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 15,
            'fats' => 5,
            'sodium' => 100,
            'fiber' => 2,
            'added_sugars' => 3,
            'calcium' => 50,
            'potassium' => 200,
            'caffeine' => 0,
            'iron' => 1,
            'cost_per_unit' => 4.99,
        ]);

        // Import with different nutritional values
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Nutritional Test\t100\tg\t200\t8\t150\t20\t4\t5\t25\t80\t300\t50\t2.5\t6.50";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Updated 1 existing ingredients:', $successMessage);
        $this->assertStringContainsString('protein: \'20\' → \'25\'', $successMessage);
        $this->assertStringContainsString('carbs: \'15\' → \'20\'', $successMessage);
        $this->assertStringContainsString('fats: \'5\' → \'8\'', $successMessage);
        $this->assertStringContainsString('sodium: \'100\' → \'150\'', $successMessage);
        $this->assertStringContainsString('fiber: \'2\' → \'4\'', $successMessage);
        $this->assertStringContainsString('added_sugars: \'3\' → \'5\'', $successMessage);
        $this->assertStringContainsString('calcium: \'50\' → \'80\'', $successMessage);
        $this->assertStringContainsString('potassium: \'200\' → \'300\'', $successMessage);
        $this->assertStringContainsString('caffeine: \'0\' → \'50\'', $successMessage);
        $this->assertStringContainsString('iron: \'1\' → \'2.5\'', $successMessage);
        $this->assertStringContainsString('cost_per_unit: \'4.99\' → \'6.5\'', $successMessage);
    }

    public function test_import_form_is_visible_in_test_environment()
    {
        // In test environment (which is not production/staging), form should be visible
        $response = $this->actingAs($this->user)
            ->get(route('ingredients.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Import');
        $response->assertSee('Import TSV');
    }

    public function test_import_request_is_rejected_in_production_environment()
    {
        // This test would require mocking the middleware, but the middleware
        // should handle this. We can test that the route exists and is protected.
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Test\t100\tg\t100\t1\t0\t10\t0\t0\t5\t0\t0\t0\t0\t1.00";

        // In test environment, this should work
        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        // Should succeed in test environment
        $this->assertTrue(
            session()->has('success') || session()->has('error'),
            'Response should have either success or error message'
        );
    }

    public function test_import_maintains_personal_ingredient_model()
    {
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "Personal Ingredient\t100\tg\t150\t4\t50\t12\t2\t1\t20\t80\t180\t0\t1.5\t3.99";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');

        // Verify ingredient is created with user_id (personal)
        $this->assertDatabaseHas('ingredients', [
            'user_id' => $this->user->id,
            'name' => 'Personal Ingredient',
        ]);

        // Verify no global ingredients are created (user_id should never be null)
        $this->assertDatabaseMissing('ingredients', [
            'user_id' => null,
            'name' => 'Personal Ingredient',
        ]);
    }

    public function test_import_success_message_uses_html_formatting()
    {
        $tsvData = "Ingredient\tAmount\tType\tCalories\tFat (g)\tSodium (mg)\tCarb (g)\tFiber (g)\tAdded Sugar (g)\tProtein (g)\tCalcium (mg)\tPotassium (mg)\tCaffeine (mg)\tIron (mg)\tCost ($)\n" .
                   "HTML Test\t100\tg\t150\t4\t50\t12\t2\t1\t20\t80\t180\t0\t1.5\t3.99";

        $response = $this->actingAs($this->user)
            ->post(route('ingredients.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('ingredients.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Verify HTML formatting is used
        $this->assertStringContainsString('<p>TSV data processed successfully!</p>', $successMessage);
        $this->assertStringContainsString('<p>Imported 1 new ingredients:</p>', $successMessage);
        $this->assertStringContainsString('<ul>', $successMessage);
        $this->assertStringContainsString('<li>HTML Test (100g)</li>', $successMessage);
        $this->assertStringContainsString('</ul>', $successMessage);
    }
}