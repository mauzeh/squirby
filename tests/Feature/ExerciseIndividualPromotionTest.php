<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExerciseIndividualPromotionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $adminUser;
    protected User $regularUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role and users
        $this->adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach($this->adminRole);
        $this->regularUser = User::factory()->create();
    }

    /** @test */
    public function admin_can_promote_individual_user_exercise_to_global()
    {
        // Create user exercise for promotion
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Admin promotes individual exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $userExercise));

        // Assert successful response
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', "Exercise 'User Exercise' promoted to global status successfully.");

        // Assert exercise is now global (user_id is null)
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'User Exercise',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function non_admin_user_cannot_access_individual_promotion()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Regular user attempts to promote exercise
        $response = $this->actingAs($this->regularUser)
            ->post(route('exercises.promote', $userExercise));

        // Assert permission denied
        $response->assertStatus(403);

        // Assert exercise remains user-specific
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function individual_promotion_fails_when_trying_to_promote_already_global_exercise()
    {
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Admin attempts to promote already global exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $globalExercise));

        // Assert authorization failure (403) because admin cannot promote global exercises
        $response->assertStatus(403);

        // Assert global exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function individual_promotion_handles_non_existent_exercise()
    {
        // Admin attempts promotion with non-existent exercise ID
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', 999)); // Non-existent ID

        // Assert 404 error for non-existent exercise
        $response->assertStatus(404);
    }

    /** @test */
    public function individual_promotion_preserves_existing_lift_logs()
    {
        // Create user exercise with lift logs
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise With Logs',
            'user_id' => $this->regularUser->id,
        ]);
        
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $userExercise->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Admin promotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $userExercise));

        // Assert successful promotion
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', "Exercise 'User Exercise With Logs' promoted to global status successfully.");

        // Assert exercise is now global
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => null,
        ]);

        // Assert lift logs are preserved and still reference the exercise
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog1->id,
            'exercise_id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
        
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog2->id,
            'exercise_id' => $userExercise->id,
            'user_id' => $this->adminUser->id,
        ]);
    }

    /** @test */
    public function individual_promotion_maintains_exercise_metadata()
    {
        // Create user exercise with specific metadata
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'description' => 'Original description',
            'user_id' => $this->regularUser->id,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(3),
            'exercise_type' => 'bodyweight'
        ]);

        $originalCreatedAt = $userExercise->created_at;
        $originalUpdatedAt = $userExercise->updated_at;

        // Admin promotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $userExercise));

        // Assert successful promotion
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', "Exercise 'User Exercise' promoted to global status successfully.");

        // Refresh exercise from database
        $userExercise->refresh();

        // Assert metadata is preserved
        $this->assertNull($userExercise->user_id); // Now global
        $this->assertEquals('User Exercise', $userExercise->title);
        $this->assertEquals('Original description', $userExercise->description);
        $this->assertEquals('bodyweight', $userExercise->exercise_type);
        $this->assertEquals($originalCreatedAt->format('Y-m-d H:i:s'), $userExercise->created_at->format('Y-m-d H:i:s'));
        // Updated_at should change due to the promotion
        $this->assertNotEquals($originalUpdatedAt->format('Y-m-d H:i:s'), $userExercise->updated_at->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function promoted_exercise_becomes_visible_to_all_users_immediately()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'Soon To Be Global Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Create another user who shouldn't see the exercise initially
        $otherUser = User::factory()->create();

        // Verify other user cannot see the user-specific exercise initially
        $this->actingAs($otherUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertDontSee('Soon To Be Global Exercise');

        // Admin promotes the exercise
        $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $userExercise));

        // Verify other user can now see the promoted exercise
        $this->actingAs($otherUser);
        $indexResponse = $this->get(route('exercises.index'));
        $indexResponse->assertSee('Soon To Be Global Exercise');
        
        // Verify it shows as global in the UI
        $indexResponse->assertSee('Global'); // Badge text
    }

    /** @test */
    public function unauthenticated_user_cannot_access_individual_promotion()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Unauthenticated user attempts promotion
        $response = $this->post(route('exercises.promote', $userExercise));

        // Should redirect to login
        $response->assertRedirect(route('login'));

        // Exercise should remain unchanged
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'user_id' => $this->regularUser->id,
        ]);
    }

    /** @test */
    public function promote_button_appears_for_admin_users_on_user_exercises()
    {
        // Create user exercises owned by admin (so they can see them)
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->adminUser->id,
        ]);

        // Admin views exercise index
        $this->actingAs($this->adminUser);
        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('User Exercise');
        
        // Should see promote button with globe icon
        $response->assertSee('fa-globe');
        $response->assertSee('Promote to global');
        
        // Should see the promote form for this exercise
        $response->assertSee(route('exercises.promote', $userExercise));
    }

    /** @test */
    public function promote_button_does_not_appear_for_non_admin_users()
    {
        // Create user exercises owned by regular user
        $userExercise = Exercise::factory()->create([
            'title' => 'User Exercise',
            'user_id' => $this->regularUser->id,
        ]);

        // Regular user views exercise index
        $this->actingAs($this->regularUser);
        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('User Exercise');
        
        // Should NOT see promote button or globe icon
        $response->assertDontSee('fa-globe');
        $response->assertDontSee('Promote to global');
        
        // Should NOT see the promote form
        $response->assertDontSee(route('exercises.promote', $userExercise));
    }

    /** @test */
    public function promote_button_does_not_appear_for_global_exercises()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Admin views exercise index
        $this->actingAs($this->adminUser);
        $response = $this->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Global Exercise');
        $response->assertSee('Global'); // Badge text
        
        // Should NOT see promote button for global exercise
        $response->assertDontSee(route('exercises.promote', $globalExercise));
    }

    /** @test */
    public function individual_promotion_success_case()
    {
        // Create user exercise
        $userExercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'user_id' => $this->regularUser->id,
        ]);

        // Admin promotes exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $userExercise));

        // Assert successful response
        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', "Exercise 'Test Exercise' promoted to global status successfully.");
        $response->assertSessionHasNoErrors();

        // Assert exercise is now global
        $this->assertDatabaseHas('exercises', [
            'id' => $userExercise->id,
            'title' => 'Test Exercise',
            'description' => 'Test description',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function individual_promotion_error_case_for_global_exercise()
    {
        // Create global exercise
        $globalExercise = Exercise::factory()->create([
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);

        // Admin attempts to promote already global exercise
        $response = $this->actingAs($this->adminUser)
            ->post(route('exercises.promote', $globalExercise));

        // Assert authorization error
        $response->assertStatus(403);

        // Assert exercise remains global
        $this->assertDatabaseHas('exercises', [
            'id' => $globalExercise->id,
            'title' => 'Global Exercise',
            'user_id' => null,
        ]);
    }
}