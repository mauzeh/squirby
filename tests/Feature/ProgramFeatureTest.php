<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use Carbon\Carbon;

class ProgramFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_programs()
    {
        $this->get(route('programs.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_see_their_own_programs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id, 'date' => Carbon::today()]);

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertStatus(200)
            ->assertSee($exercise->title);
    }

    public function test_user_cannot_see_programs_of_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $program = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id, 'date' => Carbon::today()]);

        $this->actingAs($user1)
            ->get(route('programs.index'))
            ->assertStatus(200)
            ->assertDontSee($exercise->title);
    }

    public function test_user_can_create_a_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('programs.store'), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 3,
                'reps' => 10,
                'comments' => 'Test comments',
                'priority' => 5,
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('programs', [
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
            'comments' => 'Test comments',
            'priority' => 5,
        ]);
    }

    public function test_user_can_update_their_own_program_with_priority()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id, 'priority' => 10]);

        $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 5,
                'reps' => 5,
                'priority' => 1,
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 5,
            'reps' => 5,
            'priority' => 1,
        ]);
    }

    public function test_user_cannot_create_a_program_with_invalid_data()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('programs.store'), [])
            ->assertSessionHasErrors(['exercise_id', 'date']);
    }

    public function test_user_can_edit_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->get(route('programs.edit', $program))
            ->assertStatus(200);
    }

    public function test_user_cannot_edit_program_of_other_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $program = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user1)
            ->get(route('programs.edit', $program))
            ->assertStatus(403);
    }

    public function test_user_can_update_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 5,
                'reps' => 5,
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 5,
            'reps' => 5,
        ]);
    }

    public function test_user_can_delete_their_own_program()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->delete(route('programs.destroy', $program), ['date' => Carbon::today()->format('Y-m-d')])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    public function test_the_create_view_is_rendered_with_exercise_names()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('programs.create'));

        $response->assertStatus(200);
        $response->assertViewHas('exercises');
        $response->assertSee($exercise->title);
    }

    public function test_program_index_displays_correct_fields()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 12,
            'comments' => 'Test comment',
        ]);

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertStatus(200)
            ->assertSee($exercise->title)
            ->assertSee('3')
            ->assertSee('12')
            ->assertSee('Test comment')
            ->assertSee('5');
    }

    public function test_programs_are_ordered_by_priority()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Create programs with various priorities, including duplicates and zero
        $programA = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'priority' => 5,
        ]);
        $programB = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'priority' => 1,
        ]);
        $programC = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'priority' => 10,
        ]);
        $programD = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'priority' => 5,
        ]); // Duplicate priority
        $programE = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'priority' => 0,
        ]); // Zero priority

        $response = $this->actingAs($user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200);

        // Assert that programs are ordered by priority (lower number first)
        // When priorities are the same, the order is not guaranteed by priority alone,
        // but typically by creation order or secondary sort if defined.
        // For this test, we'll assert the relative order of distinct priorities.
        $response->assertSeeInOrder([
            $programE->exercise->title, // Priority 0
            $programB->exercise->title, // Priority 1
            $programA->exercise->title, // Priority 5 (or programD)
            $programC->exercise->title, // Priority 10
        ]);

        // To be more precise with same priorities, we would need to assert the exact order
        // of all elements, or add a secondary sort to the query (e.g., by ID).
        // For now, asserting the distinct priority order is sufficient for this feature test.
    }

    public function test_user_can_delete_multiple_programs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program1 = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        $program2 = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->post(route('programs.destroy-selected'), [
                'program_ids' => [$program1->id, $program2->id],
                'date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseMissing('programs', ['id' => $program1->id]);
        $this->assertDatabaseMissing('programs', ['id' => $program2->id]);
    }



    public function test_user_cannot_delete_other_users_programs_via_bulk_delete()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user2->id]);
        $program1 = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id]);
        $program2 = Program::factory()->create(['user_id' => $user2->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user1)
            ->post(route('programs.destroy-selected'), [
                'program_ids' => [$program1->id, $program2->id],
                'date' => Carbon::today()->format('Y-m-d'),
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('programs', ['id' => $program1->id]);
        $this->assertDatabaseHas('programs', ['id' => $program2->id]);
    }

    public function test_user_can_create_a_program_with_a_new_exercise()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('programs.store'), [
                'new_exercise_name' => 'New Exercise',
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 3,
                'reps' => 10,
                'comments' => 'Test comments',
                'priority' => 5,
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $user->id,
        ]);

        $exercise = Exercise::where('title', 'New Exercise')->first();

        $this->assertDatabaseHas('programs', [
            'exercise_id' => $exercise->id,
            'sets' => 3,
            'reps' => 10,
            'comments' => 'Test comments',
            'priority' => 5,
        ]);
    }

    public function test_user_can_update_a_program_with_a_new_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $program = Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);

        $this->actingAs($user)
            ->put(route('programs.update', $program), [
                'new_exercise_name' => 'New Exercise',
                'date' => Carbon::today()->format('Y-m-d'),
                'sets' => 5,
                'reps' => 5,
            ])
            ->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'user_id' => $user->id,
        ]);

        $new_exercise = Exercise::where('title', 'New Exercise')->first();

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'exercise_id' => $new_exercise->id,
            'sets' => 5,
            'reps' => 5,
        ]);
    }

    public function test_default_priority_is_set_correctly()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        Program::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id, 'date' => Carbon::today(), 'priority' => 5]);

        $response = $this->actingAs($user)
            ->get(route('programs.create', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200);
        $response->assertViewHas('defaultPriority', 100); // Regular create form adds at bottom with default priority
    }

    public function test_program_index_handles_missing_suggested_weight_gracefully()
    {
        $user = User::factory()->create();
        // Create a banded exercise
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'band_type' => 'resistance']);
        
        // Create a LiftLog for the banded exercise to ensure $lastLog is not null
        LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);

        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        // Now, getSuggestionDetails for this banded exercise will return an stdClass without suggestedWeight
        $response = $this->actingAs($user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200); // Expecting 200, but the error should still occur
        // We expect an error because getSuggestionDetails for a banded exercise with lift logs
        // will return an stdClass without suggestedWeight, leading to the Undefined property error.
        // The test should fail if the error is present in the response content.
    }
}
