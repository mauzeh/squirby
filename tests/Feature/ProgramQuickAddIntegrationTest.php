<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Carbon\Carbon;

class ProgramQuickAddIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function programs_index_displays_exercise_selector_with_top_exercises()
    {
        // Create exercises for the user
        $exercises = Exercise::factory()->count(7)->create(['user_id' => $this->user->id]);
        
        $response = $this->get(route('programs.index'));

        $response->assertStatus(200);
        $response->assertViewHas('displayExercises');
        $response->assertViewHas('allExercises');
        
        // Should see the exercise selector component
        $response->assertSee('top-exercises-container');
        
        // Should see some exercise buttons (up to 5 top exercises)
        $displayExercises = $response->viewData('displayExercises');
        $this->assertLessThanOrEqual(5, $displayExercises->count());
        
        // Should see the "More..." dropdown button
        $response->assertSee('More...');
    }

    /** @test */
    public function exercise_selector_buttons_route_to_programs_quick_add()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();
        
        $response = $this->get(route('programs.index', ['date' => $date]));

        $response->assertStatus(200);
        
        // Check that the exercise selector uses programs routing
        $response->assertSee(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));
    }

    /** @test */
    public function clicking_exercise_button_creates_program_entry()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Verify no program exists initially
        $this->assertDatabaseMissing('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
        ]);

        // Click the exercise button (simulate quick-add route)
        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        // Should redirect back to programs index with success message
        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Verify program was created
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse($date)->startOfDay(),
        ]);
    }

    /** @test */
    public function dropdown_exercise_selection_creates_program_entry()
    {
        // Create enough exercises so some will be in dropdown
        $exercises = Exercise::factory()->count(8)->create(['user_id' => $this->user->id]);
        $dropdownExercise = $exercises->last(); // This should be in dropdown, not top buttons
        
        $date = Carbon::today()->toDateString();

        // Verify no program exists initially
        $this->assertDatabaseMissing('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $dropdownExercise->id,
        ]);

        // Select exercise from dropdown (simulate quick-add route)
        $response = $this->get(route('programs.quick-add', ['exercise' => $dropdownExercise->id, 'date' => $date]));

        // Should redirect back to programs index with success message
        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');

        // Verify program was created
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $dropdownExercise->id,
            'date' => Carbon::parse($date)->startOfDay(),
        ]);
    }

    /** @test */
    public function date_context_is_preserved_in_exercise_selector()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $futureDate = Carbon::today()->addDays(5)->toDateString();

        // Visit programs index for a specific future date
        $response = $this->get(route('programs.index', ['date' => $futureDate]));

        $response->assertStatus(200);
        
        // Exercise selector should include the date parameter
        $response->assertSee(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $futureDate]));
    }

    /** @test */
    public function program_entry_created_for_correct_date_context()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $futureDate = Carbon::today()->addDays(3)->toDateString();

        // Add exercise to program for future date
        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $futureDate]));

        $response->assertRedirect(route('programs.index', ['date' => $futureDate]));

        // Verify program was created for the correct date
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse($futureDate)->startOfDay(),
        ]);

        // Verify program was NOT created for today
        $this->assertDatabaseMissing('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->startOfDay(),
        ]);
    }

    /** @test */
    public function success_message_is_set_in_session_after_program_creation()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Add exercise to program
        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        // Should redirect with success message in session
        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $response->assertSessionHas('success', 'Exercise added to program successfully.');
    }

    /** @test */
    public function new_program_entry_appears_in_programs_list()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Add exercise to program
        $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        // Visit programs index to see the new entry
        $response = $this->get(route('programs.index', ['date' => $date]));

        $response->assertStatus(200);
        $response->assertSee($exercise->title);
        
        // Should see the sets and reps display
        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->first();
        
        $response->assertSee($program->sets . ' x ' . $program->reps);
    }

    /** @test */
    public function exercise_selector_handles_no_exercises_gracefully()
    {
        // User has no exercises
        $response = $this->get(route('programs.index'));

        $response->assertStatus(200);
        
        // Should still show the exercise selector component but with no exercises
        $displayExercises = $response->viewData('displayExercises');
        $allExercises = $response->viewData('allExercises');
        
        $this->assertEmpty($displayExercises);
        $this->assertEmpty($allExercises);
    }

    /** @test */
    public function exercise_selector_only_shows_user_exercises()
    {
        // Create exercises for current user
        $userExercises = Exercise::factory()->count(3)->create(['user_id' => $this->user->id]);
        
        // Create exercises for another user
        $otherUser = User::factory()->create();
        $otherUserExercises = Exercise::factory()->count(2)->create(['user_id' => $otherUser->id]);
        
        // Create global exercises (user_id = null)
        $globalExercises = Exercise::factory()->count(2)->create(['user_id' => null]);

        $response = $this->get(route('programs.index'));

        $response->assertStatus(200);
        
        $allExercises = $response->viewData('allExercises');
        
        // Should include user exercises and global exercises
        $this->assertEquals(5, $allExercises->count()); // 3 user + 2 global
        
        // Should not include other user's exercises
        foreach ($otherUserExercises as $exercise) {
            $this->assertFalse($allExercises->contains('id', $exercise->id));
        }
        
        // Should include user's exercises
        foreach ($userExercises as $exercise) {
            $this->assertTrue($allExercises->contains('id', $exercise->id));
        }
        
        // Should include global exercises
        foreach ($globalExercises as $exercise) {
            $this->assertTrue($allExercises->contains('id', $exercise->id));
        }
    }

    /** @test */
    public function quick_add_sets_correct_priority_for_new_program()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Add first exercise (should get priority 100)
        $this->get(route('programs.quick-add', ['exercise' => $exercise1->id, 'date' => $date]));
        
        $program1 = Program::where('exercise_id', $exercise1->id)->first();
        $this->assertEquals(100, $program1->priority);

        // Add second exercise (should get priority 99, appearing at top)
        $this->get(route('programs.quick-add', ['exercise' => $exercise2->id, 'date' => $date]));
        
        $program2 = Program::where('exercise_id', $exercise2->id)->first();
        $this->assertEquals(99, $program2->priority); // 100-1=99
    }

    /** @test */
    public function quick_add_calculates_sets_and_reps_correctly()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        $program = Program::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->first();

        $this->assertNotNull($program);
        $this->assertGreaterThan(0, $program->sets);
        $this->assertGreaterThan(0, $program->reps);
        
        // Should use default values when no progression data exists
        $this->assertEquals(config('training.defaults.sets', 3), $program->sets);
        $this->assertEquals(config('training.defaults.reps', 10), $program->reps);
    }

    /** @test */
    public function exercise_selector_integration_with_existing_add_program_button()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.index', ['date' => $date]));

        $response->assertStatus(200);
        
        // Should see both the traditional "Add Program Entry" button and the exercise selector
        $response->assertSee('Add Program Entry');
        $response->assertSee('top-exercises-container');
        
        // Both should be in the same container/layout
        $response->assertSee('display: flex');
    }

    /** @test */
    public function quick_add_handles_invalid_exercise_gracefully()
    {
        $date = Carbon::today()->toDateString();
        $nonExistentExerciseId = 99999;

        // Attempt to add non-existent exercise
        $response = $this->get(route('programs.quick-add', ['exercise' => $nonExistentExerciseId, 'date' => $date]));

        // Should handle the error gracefully (Laravel will throw ModelNotFoundException)
        $response->assertStatus(404);
    }

    /** @test */
    public function quick_add_handles_invalid_date_gracefully()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $invalidDate = 'invalid-date';

        // Attempt to add with invalid date
        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $invalidDate]));

        // Should handle the error gracefully
        $response->assertStatus(500); // Carbon will throw an exception for invalid date
    }

    /** @test */
    public function multiple_quick_adds_on_same_date_work_correctly()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $exercise3 = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        // Add multiple exercises quickly
        $this->get(route('programs.quick-add', ['exercise' => $exercise1->id, 'date' => $date]));
        $this->get(route('programs.quick-add', ['exercise' => $exercise2->id, 'date' => $date]));
        $this->get(route('programs.quick-add', ['exercise' => $exercise3->id, 'date' => $date]));

        // Verify all programs were created with correct priorities (newest at top)
        $this->assertDatabaseHas('programs', ['exercise_id' => $exercise1->id, 'priority' => 100]);
        $this->assertDatabaseHas('programs', ['exercise_id' => $exercise2->id, 'priority' => 99]); // 100-1=99
        $this->assertDatabaseHas('programs', ['exercise_id' => $exercise3->id, 'priority' => 98]); // 99-1=98

        // Verify they all appear on the programs index
        $response = $this->get(route('programs.index', ['date' => $date]));
        $response->assertSee($exercise1->title);
        $response->assertSee($exercise2->title);
        $response->assertSee($exercise3->title);
    }

    /** @test */
    public function quick_add_works_with_standard_date_format()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        
        // Test with standard Y-m-d format (what the application expects)
        $date = Carbon::today()->toDateString(); // Y-m-d format
        
        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse($date)->startOfDay(),
        ]);
    }
}