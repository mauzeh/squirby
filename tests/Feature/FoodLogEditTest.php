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
    public function it_should_not_require_ingredient_id_when_updating_a_food_log()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $response = $this->put(route('food-logs.update', $foodLog->id), [
            'quantity' => 100,
            'notes' => 'Updated notes',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('food-logs.index', ['date' => $foodLog->logged_at->format('Y-m-d')]));

        $this->assertDatabaseHas('food_logs', [
            'id' => $foodLog->id,
            'quantity' => 100,
            'notes' => 'Updated notes',
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
