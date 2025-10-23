<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;

class GlobalExerciseVisibilityToggleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create regular user
        $this->user = User::factory()->create(['show_global_exercises' => true]);
        
        // Create admin user with role
        $this->adminUser = User::factory()->create(['show_global_exercises' => false]);
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole);
    }

    /** @test */
    public function profile_settings_form_submission_updates_global_exercise_preference()
    {
        $this->actingAs($this->user);

        // Test updating preference to false
        $response = $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
        
        $this->user->refresh();
        $this->assertFalse($this->user->show_global_exercises);

        // Test updating preference to true
        $response = $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
        
        $this->user->refresh();
        $this->assertTrue($this->user->show_global_exercises);
    }

    /** @test */
    public function mobile_entry_interface_respects_user_preference_when_enabled()
    {
        // Create global and user exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'Other User Exercise']);

        // Set user preference to show global exercises
        $this->user->update(['show_global_exercises' => true]);
        $this->actingAs($this->user);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Should see both global and user exercises
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        
        // Should not see other user's exercises
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function mobile_entry_interface_respects_user_preference_when_disabled()
    {
        // Create global and user exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => User::factory()->create()->id, 'title' => 'Other User Exercise']);

        // Set user preference to hide global exercises
        $this->user->update(['show_global_exercises' => false]);
        $this->actingAs($this->user);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Should only see user exercises
        $response->assertSee('User Exercise');
        
        // Should not see global or other user's exercises
        $response->assertDontSee('Global Exercise');
        $response->assertDontSee('Other User Exercise');
    }

    /** @test */
    public function admin_users_always_see_all_exercises_regardless_of_preference()
    {
        // Create global and user exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'User Exercise']);
        $adminExercise = Exercise::factory()->create(['user_id' => $this->adminUser->id, 'title' => 'Admin Exercise']);

        // Admin has preference set to false, but should still see all exercises
        $this->assertTrue($this->adminUser->hasRole('Admin'));
        $this->assertFalse($this->adminUser->show_global_exercises);
        
        $this->actingAs($this->adminUser);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Admin should see all exercises regardless of preference
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');
        $response->assertSee('Admin Exercise');
    }

    /** @test */
    public function new_users_have_global_exercises_enabled_by_default()
    {
        // Create a new user (should have default value)
        $newUser = User::factory()->create();
        
        // Default should be true
        $this->assertTrue($newUser->shouldShowGlobalExercises());
        
        // Create exercises to test behavior
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $newUser->id, 'title' => 'New User Exercise']);
        
        $this->actingAs($newUser);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // New user should see global exercises by default
        $response->assertSee('Global Exercise');
        $response->assertSee('New User Exercise');
    }

    /** @test */
    public function profile_settings_form_displays_current_global_exercise_preference()
    {
        // Test with preference enabled
        $this->user->update(['show_global_exercises' => true]);
        $this->actingAs($this->user);

        $response = $this->get('/profile');
        
        $response->assertStatus(200);
        $response->assertSee('show_global_exercises');
        
        // Test with preference disabled
        $this->user->update(['show_global_exercises' => false]);
        
        $response = $this->get('/profile');
        
        $response->assertStatus(200);
        $response->assertSee('show_global_exercises');
    }

    /** @test */
    public function user_preference_persists_across_sessions()
    {
        $this->actingAs($this->user);

        // Update preference to false
        $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => false,
        ]);

        // Simulate logout/login by creating new request
        $this->user->refresh();
        $this->actingAs($this->user);

        // Create exercises to test behavior
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'User Exercise']);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Should still respect the saved preference (false)
        $response->assertSee('User Exercise');
        $response->assertDontSee('Global Exercise');
    }

    /** @test */
    public function mobile_entry_exercise_list_updates_immediately_after_preference_change()
    {
        // Create exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'User Exercise']);

        $this->actingAs($this->user);

        // Initially enabled - should see global exercises
        $this->user->update(['show_global_exercises' => true]);
        
        $response = $this->get(route('lift-logs.mobile-entry'));
        $response->assertSee('Global Exercise');
        $response->assertSee('User Exercise');

        // Change preference to disabled
        $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => false,
        ]);

        // Check mobile entry again - should no longer see global exercises
        $response = $this->get(route('lift-logs.mobile-entry'));
        $response->assertSee('User Exercise');
        $response->assertDontSee('Global Exercise');
    }

    /** @test */
    public function checkbox_form_behavior_works_correctly_when_unchecked()
    {
        $this->actingAs($this->user);

        // Start with preference enabled
        $this->user->update(['show_global_exercises' => true]);
        $this->assertTrue($this->user->show_global_exercises);

        // Simulate form submission with checkbox unchecked (hidden field sends "0")
        $response = $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => '0', // This is what the hidden field sends when checkbox is unchecked
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
        
        $this->user->refresh();
        $this->assertFalse($this->user->show_global_exercises);
    }

    /** @test */
    public function checkbox_form_behavior_works_correctly_when_checked()
    {
        $this->actingAs($this->user);

        // Start with preference disabled
        $this->user->update(['show_global_exercises' => false]);
        $this->assertFalse($this->user->show_global_exercises);

        // Simulate form submission with checkbox checked (checkbox value "1" overrides hidden field "0")
        $response = $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'show_global_exercises' => '1', // This is what the checkbox sends when checked
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
        
        $this->user->refresh();
        $this->assertTrue($this->user->show_global_exercises);
    }

    /** @test */
    public function profile_form_contains_required_hidden_field_for_checkbox_behavior()
    {
        $this->actingAs($this->user);

        $response = $this->get('/profile');
        
        $response->assertStatus(200);
        
        // Check that the hidden field exists and comes BEFORE the checkbox
        // This is critical for proper checkbox behavior
        $content = $response->getContent();
        
        // Look for the hidden field
        $this->assertStringContainsString(
            '<input type="hidden" name="show_global_exercises" value="0"',
            $content,
            'Hidden field for show_global_exercises is missing. This will break checkbox unchecked behavior.'
        );
        
        // Look for the checkbox field
        $this->assertStringContainsString(
            'name="show_global_exercises"',
            $content
        );
        $this->assertStringContainsString(
            'type="checkbox"',
            $content
        );
        
        // Ensure the hidden field comes BEFORE the checkbox in the HTML
        // This is crucial because when both are present, the last one wins
        $hiddenFieldPos = strpos($content, '<input type="hidden" name="show_global_exercises" value="0"');
        $checkboxFieldPos = strpos($content, 'type="checkbox"');
        
        $this->assertNotFalse($hiddenFieldPos, 'Hidden field not found in form');
        $this->assertNotFalse($checkboxFieldPos, 'Checkbox field not found in form');
        $this->assertLessThan(
            $checkboxFieldPos, 
            $hiddenFieldPos,
            'Hidden field must come BEFORE the checkbox in HTML for proper form behavior. ' .
            'When checkbox is unchecked, only hidden field value is sent. ' .
            'When checkbox is checked, checkbox value overrides hidden field value.'
        );
    }

    /** @test */
    public function form_submission_without_hidden_field_would_break_unchecked_behavior()
    {
        $this->actingAs($this->user);

        // Start with preference enabled
        $this->user->update(['show_global_exercises' => true]);
        $this->assertTrue($this->user->show_global_exercises);

        // Simulate form submission WITHOUT show_global_exercises field at all
        // This is what happens when checkbox is unchecked and there's no hidden field
        $response = $this->patch('/profile', [
            'name' => $this->user->name,
            'email' => $this->user->email,
            // Note: no show_global_exercises field at all
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/profile');
        
        $this->user->refresh();
        
        // Without the hidden field, the preference would remain unchanged (true)
        // This test documents the broken behavior that the hidden field prevents
        $this->assertTrue(
            $this->user->show_global_exercises,
            'This test demonstrates why the hidden field is necessary. ' .
            'Without it, unchecking the checkbox would not update the preference.'
        );
    }
}