<?php

namespace Tests\Feature\MobileEntry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\FoodLog;
use Carbon\Carbon;

class FoodsSummaryVisibilityTest extends TestCase
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
    public function summary_section_is_hidden_when_no_food_entries_exist()
    {
        $response = $this->get(route('mobile-entry.foods'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.flexible');
        
        // Check that summary component doesn't exist in components
        $data = $response->viewData('data');
        $summaryComponent = collect($data['components'])->firstWhere('type', 'summary');
        $this->assertNull($summaryComponent);
        
        // Verify the HTML doesn't contain summary section
        $response->assertDontSee('class="summary"');
    }

    /** @test */
    public function summary_section_is_shown_when_food_entries_exist()
    {
        // Create ingredient and unit
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast'
        ]);
        $unit = Unit::factory()->create(['name' => 'g']);

        // Create food log entry
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => Carbon::today(),
            'quantity' => 100
        ]);

        $response = $this->get(route('mobile-entry.foods'));

        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.flexible');
        
        // Check that summary exists in view data - need to extract from components
        $data = $response->viewData('data');
        $summaryComponent = collect($data['components'])->firstWhere('type', 'summary');
        $this->assertNotNull($summaryComponent);
        $this->assertIsArray($summaryComponent['data']);
        
        // Verify the HTML contains summary section
        $response->assertSee('class="summary"', false);
        
        // Check for the updated label - need to find the item with key 'calories'
        $caloriesItem = collect($summaryComponent['data']['items'])->firstWhere('key', 'calories');
        $this->assertEquals('Calories', $caloriesItem['label']);
    }

    /** @test */
    public function summary_shows_correct_macro_labels()
    {
        // Create ingredient and unit
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast'
        ]);
        $unit = Unit::factory()->create(['name' => 'g']);

        // Create food log entry
        FoodLog::factory()->create([
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'logged_at' => Carbon::today(),
            'quantity' => 100
        ]);

        $response = $this->get(route('mobile-entry.foods'));

        $data = $response->viewData('data');
        $summaryComponent = collect($data['components'])->firstWhere('type', 'summary');
        $this->assertNotNull($summaryComponent);
        
        $items = collect($summaryComponent['data']['items']);
        
        // Check all expected labels for the new macro format
        $this->assertEquals('Calories', $items->firstWhere('key', 'calories')['label']);
        $this->assertEquals('Protein (g)', $items->firstWhere('key', 'protein')['label']);
        $this->assertEquals('Carbs (g)', $items->firstWhere('key', 'carbs')['label']);
        $this->assertEquals('Fat (g)', $items->firstWhere('key', 'fats')['label']);
    }
}