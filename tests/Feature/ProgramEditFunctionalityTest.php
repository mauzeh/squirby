<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Carbon\Carbon;

class ProgramEditFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;
    protected Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Make user an admin since ProgramController requires admin access
        $adminRole = \App\Models\Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        }
        $this->user->roles()->attach($adminRole);
        
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $this->program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 4,
            'reps' => 8,
            'date' => Carbon::today(),
            'priority' => 5,
            'comments' => 'Original comments'
        ]);
    }

    /** @test */
    public function edit_method_continues_to_work_with_existing_manual_input_fields()
    {
        $response = $this->actingAs($this->user)
            ->get(route('programs.edit', $this->program));

        $response->assertStatus(200);
        $response->assertViewIs('programs.edit');
        $response->assertViewHas('program', $this->program);
        $response->assertViewHas('exercises');
        
        // Verify the form contains manual input fields for sets and reps
        $response->assertSee('name="sets"', false);
        $response->assertSee('name="reps"', false);
        $response->assertSee('value="4"', false); // Current sets value
        $response->assertSee('value="8"', false); // Current reps value
    }

    /** @test */
    public function update_method_continues_to_validate_and_save_manual_sets_reps_input()
    {
        $updateData = [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 6,
            'reps' => 12,
            'priority' => 10,
            'comments' => 'Updated comments'
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));
        $response->assertSessionHas('success', 'Program entry updated.');

        // Verify the program was updated with the manually entered values
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 6,
            'reps' => 12,
            'priority' => 10,
            'comments' => 'Updated comments'
        ]);
    }

    /** @test */
    public function update_method_validates_required_sets_and_reps_fields()
    {
        $updateData = [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->format('Y-m-d'),
            // Missing sets and reps
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertSessionHasErrors(['sets', 'reps']);
        
        // Verify the program was not updated
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'sets' => 4, // Original value
            'reps' => 8, // Original value
        ]);
    }

    /** @test */
    public function update_method_validates_sets_and_reps_are_positive_integers()
    {
        $updateData = [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 0, // Invalid: must be at least 1
            'reps' => -5, // Invalid: must be at least 1
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertSessionHasErrors(['sets', 'reps']);
        
        // Verify the program was not updated
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'sets' => 4, // Original value
            'reps' => 8, // Original value
        ]);
    }

    /** @test */
    public function no_auto_calculation_logic_is_applied_to_program_editing()
    {
        // Create some lift log history that would affect auto-calculation
        $this->createLiftLogHistory();

        $updateData = [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 2, // Different from what auto-calculation would suggest
            'reps' => 15, // Different from what auto-calculation would suggest
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify the program uses the manually entered values, not auto-calculated ones
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'sets' => 2, // Manual input value
            'reps' => 15, // Manual input value
        ]);
    }

    /** @test */
    public function edit_form_displays_current_sets_and_reps_values_as_editable()
    {
        $response = $this->actingAs($this->user)
            ->get(route('programs.edit', $this->program));

        $response->assertStatus(200);
        
        // Check that the form shows current values in editable input fields
        $response->assertSee('<input type="number" name="sets" id="sets" class="form-control" value="4" required>', false);
        $response->assertSee('<input type="number" name="reps" id="reps" class="form-control" value="8" required>', false);
    }

    /** @test */
    public function user_can_update_program_with_new_exercise_and_manual_sets_reps()
    {
        $updateData = [
            'new_exercise_name' => 'Brand New Exercise',
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 7,
            'reps' => 3,
            'priority' => 15,
            'comments' => 'New exercise test'
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify new exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'Brand New Exercise',
            'user_id' => $this->user->id,
        ]);

        $newExercise = Exercise::where('title', 'Brand New Exercise')->first();

        // Verify program was updated with manual values
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'exercise_id' => $newExercise->id,
            'sets' => 7, // Manual input
            'reps' => 3, // Manual input
            'priority' => 15,
            'comments' => 'New exercise test'
        ]);
    }

    /** @test */
    public function edit_functionality_preserves_all_existing_validation_rules()
    {
        // Test with completely invalid data
        $updateData = [
            'exercise_id' => 999999, // Non-existent exercise
            'date' => 'invalid-date',
            'sets' => 'not-a-number',
            'reps' => 'also-not-a-number',
            'priority' => 'not-a-number',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $this->program), $updateData);

        $response->assertSessionHasErrors([
            'exercise_id',
            'date', 
            'sets',
            'reps'
        ]);

        // Verify original program data is unchanged
        $this->assertDatabaseHas('programs', [
            'id' => $this->program->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 4,
            'reps' => 8,
            'priority' => 5,
            'comments' => 'Original comments'
        ]);
    }

    /**
     * Helper method to create lift log history that would affect auto-calculation
     */
    private function createLiftLogHistory(): void
    {
        // This would create history that auto-calculation logic might use
        // but should not affect the edit functionality
        \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);
    }
}