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
}
