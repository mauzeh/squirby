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

    /** @test */
    public function mobile_entry_foods_shows_edit_and_delete_buttons_for_logged_items()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $response = $this->get(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));

        $response->assertOk();
        $data = $response->viewData('data');
        
        // Find the logged items table
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $this->assertNotNull($tableComponent, 'Table component should exist');
        
        // Check that the table has rows
        $this->assertNotEmpty($tableComponent['data']['rows']);
        
        $row = $tableComponent['data']['rows'][0];
        
        // Check for actions
        $this->assertArrayHasKey('actions', $row);
        $this->assertCount(2, $row['actions']); // edit and delete
        
        // Check edit action
        $editAction = collect($row['actions'])->firstWhere('type', 'link');
        $this->assertNotNull($editAction);
        $this->assertEquals('fa-pencil', $editAction['icon']);
        $this->assertStringContainsString('food-logs/' . $foodLog->id . '/edit', $editAction['url']);
        $this->assertEquals('btn-transparent', $editAction['cssClass']);
        
        // Check delete action
        $deleteAction = collect($row['actions'])->firstWhere('type', 'form');
        $this->assertNotNull($deleteAction);
        $this->assertEquals('fa-trash', $deleteAction['icon']);
        $this->assertEquals(route('food-logs.destroy', $foodLog->id), $deleteAction['url']);
        $this->assertEquals('DELETE', $deleteAction['method']);
        $this->assertEquals('btn-transparent', $deleteAction['cssClass']);
        $this->assertTrue($deleteAction['requiresConfirm']);
    }

    /** @test */
    public function mobile_entry_foods_actions_are_compact()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create(['user_id' => $user->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $response = $this->get(route('mobile-entry.foods', ['date' => $foodLog->logged_at->format('Y-m-d')]));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertTrue($row['compact']);
    }

    /** @test */
    public function user_cannot_edit_another_users_food_log()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create(['user_id' => $user1->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user1->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $this->actingAs($user2);
        $response = $this->get(route('food-logs.edit', $foodLog->id));

        $response->assertForbidden();
    }

    /** @test */
    public function user_cannot_delete_another_users_food_log()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create(['user_id' => $user1->id]);
        $foodLog = FoodLog::factory()->create([
            'user_id' => $user1->id,
            'ingredient_id' => $ingredient->id,
        ]);

        $this->actingAs($user2);
        $response = $this->delete(route('food-logs.destroy', $foodLog->id));

        $response->assertForbidden();
    }
}
