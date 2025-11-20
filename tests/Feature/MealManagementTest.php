<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\Meal;
use App\Models\Role;

class MealManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unit = Unit::factory()->create();
    }

    /** @test */
    public function authenticated_user_only_sees_their_ingredients_in_meal_form()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $ingredient1 = Ingredient::factory()->create(['user_id' => $user1->id, 'name' => 'User1 Ingredient', 'base_unit_id' => $this->unit->id]);

        $this->actingAs($user2);
        $ingredient2 = Ingredient::factory()->create(['user_id' => $user2->id, 'name' => 'User2 Ingredient', 'base_unit_id' => $this->unit->id]);

        $response = $this->get(route('meals.create'));

        $response->assertOk();
        $response->assertSee($ingredient2->name);
        $response->assertDontSee($ingredient1->name);
    }

    /** @test */
    public function impersonated_user_meal_is_not_visible_after_adding()
    {
        // Create an admin user and a regular user
        $adminUser = User::factory()->create();
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        $adminUser->roles()->attach($adminRole);

        $regularUser = User::factory()->create();
        $athleteRole = \App\Models\Role::where('name', 'Athlete')->first();
        $regularUser->roles()->attach($athleteRole);

        // Log in as admin
        $this->actingAs($adminUser);

        // Impersonate the regular user
        $this->get(route('users.impersonate', $regularUser->id));
        // After impersonating, explicitly set the authenticated user for subsequent requests
        $this->actingAs($regularUser);
        $this->assertAuthenticatedAs($regularUser);

        // Create an ingredient for the regular user
        $ingredient = Ingredient::factory()->create([
            'user_id' => $regularUser->id,
            'base_unit_id' => $this->unit->id
        ]);

        // Add a meal as the impersonated user
        $mealName = 'Test Impersonated Meal';
        $response = $this->post(route('meals.store'), [
            'name' => $mealName,
            'comments' => 'Meal added while impersonating',
            'ingredients' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 100]
            ]
        ]);

        $response->assertRedirect(route('meals.index'));
        $response->assertSessionHas('success', 'Meal created successfully.');

        // Navigate to the meals index page as the impersonated user
        $response = $this->get(route('meals.index'));
        $response->assertOk();

        // Assert that the newly added meal is NOT visible (this confirms the bug)
        $response->assertSee($mealName);

        // Stop impersonating
        $this->get(route('users.leave-impersonate'));
        // After leaving impersonation, explicitly set the authenticated user back to admin
        $this->actingAs($adminUser);
        $this->assertAuthenticatedAs($adminUser);
    }

    /** @test */
    public function meals_index_uses_component_based_architecture()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('meals.index'));

        $response->assertOk();
        $response->assertViewIs('mobile-entry.flexible');
        $response->assertViewHas('data');
        
        $data = $response->viewData('data');
        $this->assertArrayHasKey('components', $data);
        $this->assertIsArray($data['components']);
    }

    /** @test */
    public function meals_index_displays_title_component()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $titleComponent = collect($data['components'])->firstWhere('type', 'title');
        
        $this->assertNotNull($titleComponent);
        $this->assertEquals('Meals List', $titleComponent['data']['main']);
    }

    /** @test */
    public function meals_index_displays_add_new_meal_button()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $buttonComponent = collect($data['components'])->firstWhere('type', 'button');
        
        $this->assertNotNull($buttonComponent);
        $this->assertEquals('Add New Meal', $buttonComponent['data']['text']);
        $this->assertEquals(route('meals.create'), $buttonComponent['data']['url']);
    }

    /** @test */
    public function meals_index_displays_session_success_message()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->withSession(['success' => 'Meal created successfully.'])
            ->get(route('meals.index'));

        $data = $response->viewData('data');
        $messagesComponent = collect($data['components'])->firstWhere('type', 'messages');
        
        $this->assertNotNull($messagesComponent);
        $this->assertCount(1, $messagesComponent['data']['messages']);
        $this->assertEquals('success', $messagesComponent['data']['messages'][0]['type']);
        $this->assertEquals('Meal created successfully.', $messagesComponent['data']['messages'][0]['text']);
    }

    /** @test */
    public function meals_index_displays_table_with_meals()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create([
            'user_id' => $user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 25,
            'carbs' => 30,
            'fats' => 10,
            'base_quantity' => 100
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Meal',
            'comments' => 'Test comments'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 150]);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        
        $this->assertNotNull($tableComponent);
        $this->assertCount(1, $tableComponent['data']['rows']);
        $this->assertEquals('Test Meal', $tableComponent['data']['rows'][0]['line1']);
    }

    /** @test */
    public function meals_index_displays_macro_badges_for_each_meal()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create([
            'user_id' => $user->id,
            'base_unit_id' => $this->unit->id,
            'protein' => 20,
            'carbs' => 30,
            'fats' => 10,
            'base_quantity' => 100
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Macro Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertArrayHasKey('badges', $row);
        $this->assertCount(5, $row['badges']); // calories, protein, carbs, fats, cost
        
        // Check badge content
        $badgeTexts = collect($row['badges'])->pluck('text')->toArray();
        $this->assertStringContainsString('cal', $badgeTexts[0]);
        $this->assertStringContainsString('g P', $badgeTexts[1]);
        $this->assertStringContainsString('g C', $badgeTexts[2]);
        $this->assertStringContainsString('g F', $badgeTexts[3]);
        $this->assertStringContainsString('$', $badgeTexts[4]);
    }

    /** @test */
    public function meals_index_displays_ingredient_summary_as_subitem()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient1 = Ingredient::factory()->create([
            'user_id' => $user->id,
            'name' => 'Chicken',
            'base_unit_id' => $this->unit->id
        ]);

        $ingredient2 = Ingredient::factory()->create([
            'user_id' => $user->id,
            'name' => 'Rice',
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Chicken and Rice'
        ]);
        $meal->ingredients()->attach($ingredient1->id, ['quantity' => 150]);
        $meal->ingredients()->attach($ingredient2->id, ['quantity' => 200]);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertArrayHasKey('subItems', $row);
        $this->assertCount(1, $row['subItems']);
        $this->assertEquals('Ingredients:', $row['subItems'][0]['line1']);
        $this->assertArrayHasKey('messages', $row['subItems'][0]);
    }

    /** @test */
    public function meals_index_shows_empty_message_when_no_meals()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        
        $this->assertNotNull($tableComponent);
        $this->assertEquals('No meals found. Please add some!', $tableComponent['data']['emptyMessage']);
        $this->assertEmpty($tableComponent['data']['rows']);
    }

    /** @test */
    public function meals_index_only_shows_users_own_meals()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $ingredient = Ingredient::factory()->create([
            'user_id' => $user1->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal1 = Meal::factory()->create([
            'user_id' => $user1->id,
            'name' => 'User 1 Meal'
        ]);
        $meal1->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $meal2 = Meal::factory()->create([
            'user_id' => $user2->id,
            'name' => 'User 2 Meal'
        ]);

        $this->actingAs($user1);
        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        
        $this->assertCount(1, $tableComponent['data']['rows']);
        $this->assertEquals('User 1 Meal', $tableComponent['data']['rows'][0]['line1']);
    }

    /** @test */
    public function meals_index_table_has_edit_and_delete_actions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create([
            'user_id' => $user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Action Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertArrayHasKey('actions', $row);
        $this->assertCount(2, $row['actions']); // edit and delete
        
        // Check edit action
        $editAction = collect($row['actions'])->firstWhere('type', 'link');
        $this->assertNotNull($editAction);
        $this->assertEquals('fa-pencil', $editAction['icon']);
        $this->assertEquals(route('meals.edit', $meal->id), $editAction['url']);
        
        // Check delete action
        $deleteAction = collect($row['actions'])->firstWhere('type', 'form');
        $this->assertNotNull($deleteAction);
        $this->assertEquals('fa-trash', $deleteAction['icon']);
        $this->assertEquals(route('meals.destroy', $meal->id), $deleteAction['url']);
        $this->assertEquals('DELETE', $deleteAction['method']);
        $this->assertTrue($deleteAction['requiresConfirm']);
    }

    /** @test */
    public function meals_index_actions_are_compact()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $ingredient = Ingredient::factory()->create([
            'user_id' => $user->id,
            'base_unit_id' => $this->unit->id
        ]);

        $meal = Meal::factory()->create([
            'user_id' => $user->id,
            'name' => 'Compact Test Meal'
        ]);
        $meal->ingredients()->attach($ingredient->id, ['quantity' => 100]);

        $response = $this->get(route('meals.index'));

        $data = $response->viewData('data');
        $tableComponent = collect($data['components'])->firstWhere('type', 'table');
        $row = $tableComponent['data']['rows'][0];
        
        $this->assertTrue($row['compact']);
    }
}
