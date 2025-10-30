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
        $response->assertViewIs('mobile-entry.index');
        
        // Check that summary is null in view data
        $data = $response->viewData('data');
        $this->assertNull($data['summary']);
        
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
        $response->assertViewIs('mobile-entry.index');
        
        // Check that summary exists in view data
        $data = $response->viewData('data');
        $this->assertNotNull($data['summary']);
        $this->assertIsArray($data['summary']);
        
        // Verify the HTML contains summary section
        $response->assertSee('class="summary"', false);
        
        // Check for the updated label
        $this->assertEquals('7-Day Avg', $data['summary']['labels']['average']);
    }

    /** @test */
    public function summary_shows_correct_labels_including_seven_day_average()
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
        $summary = $data['summary'];
        
        // Check all expected labels
        $this->assertEquals('Calories', $summary['labels']['total']);
        $this->assertEquals('Entries', $summary['labels']['completed']);
        $this->assertEquals('7-Day Avg', $summary['labels']['average']);
        $this->assertEquals('Protein (g)', $summary['labels']['today']);
    }
}