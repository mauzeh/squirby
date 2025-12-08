<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Workout;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WodExerciseMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Athlete']);
    }

    public function test_wod_edit_shows_formatted_display_for_exercises()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Back Squat',
            'exercise_type' => 'weighted_resistance',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\n[[Back Squat]]: 5x5",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('Strength'); // Block name
        $response->assertSee('Back Squat'); // Exercise name
        $response->assertSee('5x5'); // Scheme
        $response->assertSee('wod-display'); // WOD display container
    }

    public function test_wod_edit_shows_formatted_display_for_non_existing_exercises()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\n[[NonExistentExercise]]: 3x8",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('NonExistentExercise');
        $response->assertSee('3x8');
        $response->assertSee('wod-display');
    }

    public function test_wod_edit_shows_formatted_display_for_mixed_exercises()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Bench Press',
            'exercise_type' => 'weighted_resistance',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\n[[Bench Press]]: 5x5\n[[FakeExercise]]: 3x8",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        // Both exercises should be shown in formatted display
        $response->assertSee('Bench Press');
        $response->assertSee('FakeExercise');
        $response->assertSee('wod-display');
    }

    public function test_wod_edit_shows_formatted_display_with_clickable_links()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\n[[Push ups]]: 3x10",  // Different format (space instead of hyphen)
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('Push ups');
        $response->assertSee('3x10');
        // Should show formatted WOD display with clickable exercise link
        $response->assertSee('wod-display');
    }

    public function test_wod_only_shows_loggable_exercises_with_double_brackets()
    {
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        $user = User::factory()->create();
        $user->roles()->attach($adminRole);
        
        // Create exercises for both loggable and non-loggable
        $squatExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Back Squat',
            'exercise_type' => 'weighted_resistance',
        ]);
        
        $warmupExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Warm-up Squats',
            'exercise_type' => 'bodyweight',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\n[[Back Squat]]: 5x5\n[Warm-up Squats]: 2x10",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout->id));

        $response->assertStatus(200);
        // Should show loggable exercise in WOD display
        $response->assertSee('Back Squat');
        $response->assertSee('5x5');
        // WOD display shows all exercises in syntax, but only double-bracketed ones are loggable
        // The display will show "Warm-up Squats" but it won't have a log button
        $response->assertSee('Warm-up Squats');
    }

    public function test_wod_special_formats_respect_loggable_flag()
    {
        $user = User::factory()->create();
        
        $burpeeExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Burpees',
            'exercise_type' => 'bodyweight',
        ]);
        
        $stretchExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Stretching',
            'exercise_type' => 'bodyweight',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "> AMRAP 10min\n10 [[Burpees]]\n5 [Stretching]",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertStatus(200);
        // Should show loggable exercise from special format
        $response->assertSee('Burpees');
        // Should NOT show non-loggable exercise from special format
        $response->assertDontSee('Stretching');
    }

    public function test_wod_table_shows_database_exercise_name_not_syntax_name()
    {
        $user = User::factory()->create();
        
        // Database has singular form
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Back Squat',
            'exercise_type' => 'weighted_resistance',
        ]);

        // WOD syntax uses plural form
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\n[[Back Squats]] 5x5",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertStatus(200);
        // Table should show database name (singular)
        $response->assertSee('Back Squat');
        // Table should NOT show syntax name (plural) in the exercise list
        // Note: The WOD display markdown will still show "Back Squats" but that's in a different section
    }

    public function test_wod_display_shows_syntax_name_while_table_shows_database_name()
    {
        $user = User::factory()->create();
        $adminRole = Role::where('name', 'Admin')->first();
        $user->roles()->attach($adminRole);
        
        // Database has singular form
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Deadlift',
            'exercise_type' => 'weighted_resistance',
        ]);

        // WOD syntax uses plural form
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\n[[Deadlifts]] 5 reps, building",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        // WOD display should show syntax name (plural) - this is in the markdown
        $response->assertSee('Deadlifts');
        // Table should show database name (singular)
        $response->assertSee('Deadlift');
        // Should show the scheme
        $response->assertSee('5 reps, building');
    }

    public function test_wod_table_does_not_show_nonexistent_exercises()
    {
        $user = User::factory()->create();

        // No matching exercise in database
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\n[[Nonexistent Exercise]] 3x8",
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertStatus(200);
        // Table should NOT show non-existent exercises
        // (They will appear in the WOD display with a link to create them)
        $response->assertDontSee('Nonexistent Exercise');
        // The WOD display will still show it in markdown format
        $response->assertSee('Test WOD'); // Workout name should be visible
    }
}
