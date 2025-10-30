<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use Carbon\Carbon;

class FoodLogMobileEntryBugTest extends TestCase
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
    public function user_can_log_ingredient_from_mobile_entry_without_undefined_variable_error()
    {
        // Create ingredient with base unit
        $unit = Unit::factory()->create(['name' => 'g']);
        $ingredient = Ingredient::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Chicken Breast',
            'base_unit_id' => $unit->id
        ]);

        $today = Carbon::today();

        // This should reproduce the undefined variable $foodLog error
        $response = $this->post(route('food-logs.store'), [
            'ingredient_id' => $ingredient->id,
            'quantity' => 150,
            'logged_at' => '12:30',
            'date' => $today->toDateString(),
            'notes' => 'Grilled chicken',
            'redirect_to' => 'mobile-entry-foods'
        ]);

        // Should redirect successfully without error
        $response->assertRedirect(route('mobile-entry.foods', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        // Verify the food log was created
        $this->assertDatabaseHas('food_logs', [
            'user_id' => $this->user->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 150,
            'notes' => 'Grilled chicken'
        ]);
    }
}