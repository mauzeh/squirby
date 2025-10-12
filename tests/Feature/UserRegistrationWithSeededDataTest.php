<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRegistrationWithSeededDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_registration_creates_default_seeded_data()
    {
        // Verify no data exists before registration
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('ingredients', 0);
        $this->assertDatabaseCount('measurement_types', 0);
        $this->assertDatabaseCount('meals', 0);

        // Register a new user
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Verify registration was successful
        $response->assertRedirect(route('food-logs.index', absolute: false));
        $this->assertAuthenticated();

        // Get the created user
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        // Verify measurement types were created
        $this->assertCount(2, $user->measurementTypes);
        $this->assertTrue($user->measurementTypes->contains('name', 'Bodyweight'));
        $this->assertTrue($user->measurementTypes->contains('name', 'Waist'));

        // Verify default ingredients were created
        $this->assertCount(5, $user->ingredients);
        $expectedIngredients = [
            'Chicken Breast',
            'Rice (dry, brown)',
            'Broccoli (raw)',
            'Olive Oil',
            'Egg (whole, large)'
        ];
        
        foreach ($expectedIngredients as $ingredientName) {
            $this->assertTrue(
                $user->ingredients->contains('name', $ingredientName),
                "Expected ingredient '{$ingredientName}' was not found"
            );
        }

        // Verify sample meal was created
        $this->assertCount(1, $user->meals);
        $meal = $user->meals->first();
        $this->assertEquals('Chicken, Rice & Broccoli', $meal->name);
        $this->assertEquals('A balanced meal with protein, carbs, and vegetables.', $meal->comments);

        // Verify meal has the correct ingredients attached
        $this->assertCount(4, $meal->ingredients);
        $mealIngredientNames = $meal->ingredients->pluck('name')->toArray();
        $expectedMealIngredients = ['Chicken Breast', 'Rice (dry, brown)', 'Broccoli (raw)', 'Olive Oil'];
        
        foreach ($expectedMealIngredients as $ingredientName) {
            $this->assertContains($ingredientName, $mealIngredientNames);
        }

        // Verify ingredient quantities in the meal
        $chickenPivot = $meal->ingredients()->where('name', 'Chicken Breast')->first()->pivot;
        $ricePivot = $meal->ingredients()->where('name', 'Rice (dry, brown)')->first()->pivot;
        $broccoliPivot = $meal->ingredients()->where('name', 'Broccoli (raw)')->first()->pivot;
        $oilPivot = $meal->ingredients()->where('name', 'Olive Oil')->first()->pivot;

        $this->assertEquals(150, $chickenPivot->quantity);
        $this->assertEquals(100, $ricePivot->quantity);
        $this->assertEquals(200, $broccoliPivot->quantity);
        $this->assertEquals(10, $oilPivot->quantity);
    }

    public function test_admin_created_user_also_gets_seeded_data()
    {
        // Create an admin user
        $admin = User::factory()->create();
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin->roles()->attach($adminRole);

        // Admin creates a new user
        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Admin Created User',
            'email' => 'admin-created@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => [$adminRole->id],
        ]);

        $response->assertRedirect(route('users.index'));

        // Get the created user
        $user = User::where('email', 'admin-created@example.com')->first();
        $this->assertNotNull($user);

        // Verify the user got seeded data
        $this->assertCount(2, $user->measurementTypes);
        $this->assertCount(5, $user->ingredients);
        $this->assertCount(1, $user->meals);

        // Verify specific seeded data
        $this->assertTrue($user->measurementTypes->contains('name', 'Bodyweight'));
        $this->assertTrue($user->ingredients->contains('name', 'Chicken Breast'));
        $this->assertEquals('Chicken, Rice & Broccoli', $user->meals->first()->name);
    }

    public function test_multiple_users_get_independent_seeded_data()
    {
        // Register first user
        $response1 = $this->post('/register', [
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response1->assertRedirect();

        // Logout first user
        auth()->logout();

        // Register second user
        $response2 = $this->post('/register', [
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response2->assertRedirect();

        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();

        $this->assertNotNull($user1, 'User 1 should exist');
        $this->assertNotNull($user2, 'User 2 should exist');

        // Verify both users have their own seeded data
        $this->assertCount(5, $user1->ingredients);
        $this->assertCount(5, $user2->ingredients);
        $this->assertCount(2, $user1->measurementTypes);
        $this->assertCount(2, $user2->measurementTypes);
        $this->assertCount(1, $user1->meals);
        $this->assertCount(1, $user2->meals);

        // Verify the data is independent (different IDs)
        $user1IngredientIds = $user1->ingredients->pluck('id')->toArray();
        $user2IngredientIds = $user2->ingredients->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($user1IngredientIds, $user2IngredientIds));

        $user1MealIds = $user1->meals->pluck('id')->toArray();
        $user2MealIds = $user2->meals->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($user1MealIds, $user2MealIds));
    }
}