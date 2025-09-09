<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;

class FoodLogImportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function authenticated_user_can_import_food_logs()
    {
        $unit = Unit::factory()->create();
        $ingredient1 = Ingredient::factory()->create(['user_id' => $this->user->id, 'name' => 'Apple', 'base_unit_id' => $unit->id]);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $this->user->id, 'name' => 'Banana', 'base_unit_id' => $unit->id]);

        $tsvData = "09/07/2025\t10:00\tApple\tNote 1\t100\n" .
                  "09/07/2025\t12:00\tBanana\tNote 2\t150";

        $response = $this->post(route('food-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-09-07']));
        $response->assertSessionHas('success', 'TSV data imported successfully!');

        $this->assertDatabaseCount('food_logs', 2);
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient1->id,
            'quantity' => 100,
            'notes' => 'Note 1',
        ]);
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient2->id,
            'quantity' => 150,
            'notes' => 'Note 2',
        ]);
    }

    /** @test */
    public function it_returns_error_for_empty_tsv_data()
    {
        $response = $this->post(route('food-logs.import-tsv'), [
            'tsv_data' => '',
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-09-07']));
        $response->assertSessionHas('error', 'TSV data cannot be empty.');
    }

    /** @test */
    public function it_returns_error_for_not_found_ingredients()
    {
        $tsvData = "09/07/2025\t10:00\tNonExistentIngredient\tNote 1\t100";

        $response = $this->post(route('food-logs.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => '2025-09-07',
        ]);

        $response->assertRedirect(route('food-logs.index', ['date' => '2025-09-07']));
        $response->assertSessionHas('error', 'No ingredients found for: NonExistentIngredient');
    }
}