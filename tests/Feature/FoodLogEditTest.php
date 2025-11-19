<?php

namespace Tests\Feature;

use App\Models\FoodLog;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodLogEditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_update_a_food_log_and_redirect_correctly()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        // Test redirect to mobile entry
        $response = $this->put(route('food-logs.update', $foodLog->id), [
            'quantity' => 100,
            'notes' => 'Updated notes',
            'redirect_to' => 'mobile-entry.foods',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseHas('food_logs', [
            'id' => $foodLog->id,
            'quantity' => 100,
            'notes' => 'Updated notes',
        ]);

        // Test redirect to index
        $response = $this->put(route('food-logs.update', $foodLog->id), [
            'quantity' => 150,
            'notes' => 'Updated notes again',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('food-logs.index', ['date' => $foodLog->logged_at->format('Y-m-d')]));
        $this->assertDatabaseHas('food_logs', [
            'id' => $foodLog->id,
            'quantity' => 150,
            'notes' => 'Updated notes again',
        ]);
    }

    /** @test */
    public function it_should_show_the_mobile_friendly_edit_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);
        $foodLog->load('ingredient');

        $response = $this->get(route('food-logs.edit', $foodLog->id));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');

        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        $this->assertCount(2, $data['components']);
        $this->assertEquals('title', $data['components'][0]['type']);
        $this->assertEquals('form', $data['components'][1]['type']);
        $this->assertEquals($foodLog->ingredient->name, $data['components'][1]['data']['title']);
    }
}
