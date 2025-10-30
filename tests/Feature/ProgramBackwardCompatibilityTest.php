<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;

class ProgramBackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

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
    }

    /** @test */
    public function existing_program_entries_display_correctly_without_modification()
    {
        // Create existing programs with various sets/reps combinations
        $programs = [
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 3,
                'reps' => 10,
                'date' => Carbon::today(),
                'priority' => 100,
                'comments' => 'Standard program'
            ]),
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 5,
                'reps' => 5,
                'date' => Carbon::today(),
                'priority' => 200,
                'comments' => 'Strength program'
            ]),
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 2,
                'reps' => 15,
                'date' => Carbon::today(),
                'priority' => 300,
                'comments' => 'Endurance program'
            ])
        ];

        $response = $this->actingAs($this->user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200);
        
        // Verify all existing programs display their original values
        foreach ($programs as $program) {
            $response->assertSee($program->sets);
            $response->assertSee($program->reps);
            $response->assertSee($program->comments);
        }

        // Verify database values remain unchanged
        foreach ($programs as $program) {
            $this->assertDatabaseHas('programs', [
                'id' => $program->id,
                'sets' => $program->sets,
                'reps' => $program->reps,
                'comments' => $program->comments,
                'priority' => $program->priority
            ]);
        }
    }

    /** @test */
    public function editing_existing_programs_works_with_unchanged_manual_input_functionality()
    {
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 4,
            'reps' => 8,
            'date' => Carbon::today(),
            'comments' => 'Original program'
        ]);

        // Test that edit form loads correctly with manual input fields
        $response = $this->actingAs($this->user)
            ->get(route('programs.edit', $program));

        $response->assertStatus(200);
        $response->assertViewIs('programs.edit');
        
        // Verify manual input fields are present and populated
        $response->assertSee('name="sets"', false);
        $response->assertSee('name="reps"', false);
        $response->assertSee('value="4"', false);
        $response->assertSee('value="8"', false);

        // Test that update works with manual input
        $updateData = [
            'exercise_id' => $this->exercise->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 6,
            'reps' => 12,
            'priority' => 150,
            'comments' => 'Updated program'
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $program), $updateData);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));
        $response->assertSessionHas('success', 'Program entry updated.');

        // Verify the program was updated with manual values
        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'sets' => 6,
            'reps' => 12,
            'priority' => 150,
            'comments' => 'Updated program'
        ]);
    }

    /** @test */
    public function no_existing_program_data_is_automatically_modified()
    {
        // Create programs with various dates and values
        $programs = collect();
        
        for ($i = 0; $i < 5; $i++) {
            $programs->push(Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => rand(1, 10),
                'reps' => rand(5, 20),
                'date' => Carbon::today()->subDays($i),
                'priority' => ($i + 1) * 100,
                'comments' => "Program from {$i} days ago"
            ]));
        }

        // Create some lift log history that might trigger auto-calculation logic
        $this->createLiftLogHistory();

        // Access various program pages that might trigger modifications
        $this->actingAs($this->user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->actingAs($this->user)
            ->get(route('programs.index', ['date' => Carbon::yesterday()->format('Y-m-d')]));

        $this->actingAs($this->user)
            ->get(route('programs.create', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify all existing programs remain unchanged
        foreach ($programs as $program) {
            $this->assertDatabaseHas('programs', [
                'id' => $program->id,
                'sets' => $program->sets,
                'reps' => $program->reps,
                'priority' => $program->priority,
                'comments' => $program->comments
            ]);
        }
    }

    /** @test */
    public function all_existing_edit_functionality_continues_to_work_as_expected()
    {
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 3,
            'reps' => 10,
            'date' => Carbon::today()
        ]);

        // Test validation still works for edit
        $invalidData = [
            'exercise_id' => 999999, // Non-existent
            'date' => 'invalid-date',
            'sets' => 0, // Invalid
            'reps' => -1, // Invalid
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $program), $invalidData);

        $response->assertSessionHasErrors(['exercise_id', 'date', 'sets', 'reps']);

        // Test creating new exercise during edit still works
        $newExerciseData = [
            'new_exercise_name' => 'New Exercise via Edit',
            'date' => Carbon::today()->format('Y-m-d'),
            'sets' => 4,
            'reps' => 6,
            'priority' => 200
        ];

        $response = $this->actingAs($this->user)
            ->put(route('programs.update', $program), $newExerciseData);

        $response->assertRedirect(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify new exercise was created
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise via Edit',
            'user_id' => $this->user->id
        ]);

        $newExercise = Exercise::where('title', 'New Exercise via Edit')->first();

        // Verify program was updated with manual values
        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'exercise_id' => $newExercise->id,
            'sets' => 4,
            'reps' => 6,
            'priority' => 200
        ]);
    }

    /** @test */
    public function existing_programs_with_edge_case_values_remain_unchanged()
    {
        // Create programs with edge case values that should be preserved
        $edgeCasePrograms = [
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 1, // Minimum value
                'reps' => 1, // Minimum value
                'date' => Carbon::today(),
                'priority' => 1,
                'comments' => 'Minimum values program'
            ]),
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 50, // High value
                'reps' => 100, // High value
                'date' => Carbon::today(),
                'priority' => 9999,
                'comments' => 'High values program'
            ]),
            Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => 7, // Odd number
                'reps' => 13, // Odd number
                'date' => Carbon::today(),
                'priority' => 777,
                'comments' => null // Null comments
            ])
        ];

        // Access the programs index to ensure no automatic modifications occur
        $response = $this->actingAs($this->user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $response->assertStatus(200);

        // Verify all edge case values are preserved exactly
        foreach ($edgeCasePrograms as $program) {
            $this->assertDatabaseHas('programs', [
                'id' => $program->id,
                'sets' => $program->sets,
                'reps' => $program->reps,
                'priority' => $program->priority,
                'comments' => $program->comments
            ]);
        }
    }

    /** @test */
    public function existing_programs_from_different_dates_remain_unaffected()
    {
        $dates = [
            Carbon::today()->subWeek(),
            Carbon::today()->subDays(3),
            Carbon::yesterday(),
            Carbon::today(),
            Carbon::tomorrow(),
            Carbon::today()->addWeek()
        ];

        $programs = collect();
        
        foreach ($dates as $date) {
            $programs->push(Program::factory()->create([
                'user_id' => $this->user->id,
                'exercise_id' => $this->exercise->id,
                'sets' => rand(2, 8),
                'reps' => rand(6, 15),
                'date' => $date,
                'priority' => rand(100, 500),
                'comments' => "Program for {$date->format('Y-m-d')}"
            ]));
        }

        // Navigate through different dates
        foreach ($dates as $date) {
            $this->actingAs($this->user)
                ->get(route('programs.index', ['date' => $date->format('Y-m-d')]));
        }

        // Create new programs for some dates (should not affect existing ones)
        $this->actingAs($this->user)
            ->get(route('programs.create', ['date' => Carbon::today()->format('Y-m-d')]));

        // Verify all existing programs remain unchanged
        foreach ($programs as $program) {
            $this->assertDatabaseHas('programs', [
                'id' => $program->id,
                'sets' => $program->sets,
                'reps' => $program->reps,
                'priority' => $program->priority,
                'comments' => $program->comments
            ]);
        }
    }

    /** @test */
    public function existing_program_relationships_remain_intact()
    {
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::factory()->create(['user_id' => $this->user->id]);

        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'sets' => 4,
            'reps' => 8,
            'date' => Carbon::today()
        ]);

        // Access various program operations
        $this->actingAs($this->user)
            ->get(route('programs.index', ['date' => Carbon::today()->format('Y-m-d')]));

        $this->actingAs($this->user)
            ->get(route('programs.edit', $program));

        // Verify relationships are preserved
        $program->refresh();
        $this->assertEquals($this->user->id, $program->user_id);
        $this->assertEquals($this->exercise->id, $program->exercise_id);
        $this->assertEquals($this->user->id, $program->user->id);
        $this->assertEquals($this->exercise->id, $program->exercise->id);
        $this->assertEquals($this->exercise->title, $program->exercise->title);
    }

    /**
     * Helper method to create lift log history
     */
    private function createLiftLogHistory(): void
    {
        $liftLog = LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'logged_at' => Carbon::yesterday(),
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 8
        ]);

        LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 7
        ]);
    }
}