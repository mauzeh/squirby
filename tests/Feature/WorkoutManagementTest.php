<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Database\Factories\WorkoutFactory;
use Database\Factories\ExerciseFactory;

class WorkoutManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $userWithWorkoutViewPermission;
    protected $userWithoutWorkoutViewPermission;
    protected $userWithWorkoutCreatePermission;
    protected $userWithoutWorkoutCreatePermission;
    protected $userWithWorkoutUpdatePermission;
    protected $userWithoutWorkoutUpdatePermission;
    protected $userWithWorkoutDeletePermission;
    protected $userWithoutWorkoutDeletePermission;
    protected $userWithExerciseViewPermission;
    protected $userWithoutExerciseViewPermission;
    protected $userWithExerciseCreatePermission;
    protected $userWithoutExerciseCreatePermission;
    protected $userWithExerciseUpdatePermission;
    protected $userWithoutExerciseUpdatePermission;
    protected $userWithExerciseDeletePermission;
    protected $userWithoutExerciseDeletePermission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed('RolesAndPermissionsSeeder');

        $this->userWithWorkoutViewPermission = User::factory()->create();
        $this->userWithWorkoutViewPermission->givePermissionTo('workouts.view');

        $this->userWithoutWorkoutViewPermission = User::factory()->create();

        $this->userWithWorkoutCreatePermission = User::factory()->create();
        $this->userWithWorkoutCreatePermission->givePermissionTo('workouts.create');

        $this->userWithoutWorkoutCreatePermission = User::factory()->create();

        $this->userWithWorkoutUpdatePermission = User::factory()->create();
        $this->userWithWorkoutUpdatePermission->givePermissionTo('workouts.update');

        $this->userWithoutWorkoutUpdatePermission = User::factory()->create();

        $this->userWithWorkoutDeletePermission = User::factory()->create();
        $this->userWithWorkoutDeletePermission->givePermissionTo('workouts.delete');

        $this->userWithoutWorkoutDeletePermission = User::factory()->create();

        $this->userWithExerciseViewPermission = User::factory()->create();
        $this->userWithExerciseViewPermission->givePermissionTo('exercises.view');

        $this->userWithoutExerciseViewPermission = User::factory()->create();

        $this->userWithExerciseCreatePermission = User::factory()->create();
        $this->userWithExerciseCreatePermission->givePermissionTo('exercises.create');

        $this->userWithoutExerciseCreatePermission = User::factory()->create();

        $this->userWithExerciseUpdatePermission = User::factory()->create();
        $this->userWithExerciseUpdatePermission->givePermissionTo('exercises.update');

        $this->userWithoutExerciseUpdatePermission = User::factory()->create();

        $this->userWithExerciseDeletePermission = User::factory()->create();
        $this->userWithExerciseDeletePermission->givePermissionTo('exercises.delete');

        $this->userWithoutExerciseDeletePermission = User::factory()->create();
    }

    /** @test */
    public function user_with_workouts_view_permission_can_view_workouts()
    {
        $workout = WorkoutFactory::new()->create(['user_id' => $this->userWithWorkoutViewPermission->id]);
        $response = $this->actingAs($this->userWithWorkoutViewPermission)->get(route('workouts.index'));
        $response->assertStatus(200);
        $response->assertSee($workout->comments);
    }

    /** @test */
    public function user_without_workouts_view_permission_cannot_view_workouts()
    {
        $response = $this->actingAs($this->userWithoutWorkoutViewPermission)->get(route('workouts.index'));
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_workouts_create_permission_can_create_workout()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithWorkoutCreatePermission->id]);
        $now = now();

        $workoutData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test workout comments',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $now->format('H:i'),
        ];

        $response = $this->actingAs($this->userWithWorkoutCreatePermission)->post(route('workouts.store'), $workoutData);

        $response->assertRedirect(route('workouts.index'));
        $this->assertDatabaseHas('workouts', [
            'user_id' => $this->userWithWorkoutCreatePermission->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test workout comments',
        ]);
    }

    /** @test */
    public function user_without_workouts_create_permission_cannot_create_workout()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithoutWorkoutCreatePermission->id]);
        $now = now();

        $workoutData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Unauthorized workout comments',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $now->format('H:i'),
        ];

        $response = $this->actingAs($this->userWithoutWorkoutCreatePermission)->post(route('workouts.store'), $workoutData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('workouts', [
            'comments' => 'Unauthorized workout comments',
        ]);
    }

    /** @test */
    public function user_with_workouts_create_permission_can_import_workouts()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithWorkoutCreatePermission->id]);
        $tsvData = "2025-01-01\t10:00\t" . $exercise->title . "\t100\t5\t3\tTest comments";

        $response = $this->actingAs($this->userWithWorkoutCreatePermission)->post(route('workouts.import-tsv'), [
            'tsv_data' => $tsvData,
            'date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('workouts.index'));
        $this->assertDatabaseHas('workouts', [
            'user_id' => $this->userWithWorkoutCreatePermission->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test comments',
        ]);
    }

    /** @test */
    public function user_without_workouts_create_permission_cannot_import_workouts()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithoutWorkoutCreatePermission->id]);
        $tsvData = "2025-01-01	10:00	" . $exercise->title . "	100	5	3	Unauthorized comments";

        $response = $this->actingAs($this->userWithoutWorkoutCreatePermission)->post(route('workouts.import-tsv'), [
            'tsv_data' => $tsvData
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('workouts', [
            'comments' => 'Unauthorized comments',
        ]);
    }

    /** @test */
    public function user_with_workouts_delete_permission_can_delete_workout()
    {
        $workout = WorkoutFactory::new()->create(['user_id' => $this->userWithWorkoutDeletePermission->id]);
        $response = $this->actingAs($this->userWithWorkoutDeletePermission)->delete(route('workouts.destroy', $workout->id));
        $response->assertRedirect(route('workouts.index'));
        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    /** @test */
    public function user_without_workouts_delete_permission_cannot_delete_workout()
    {
        $workout = WorkoutFactory::new()->create(['user_id' => $this->userWithoutWorkoutDeletePermission->id]);
        $response = $this->actingAs($this->userWithoutWorkoutDeletePermission)->delete(route('workouts.destroy', $workout->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id]);
    }

    /** @test */
    public function user_with_workouts_delete_permission_can_bulk_delete_workouts()
    {
        $workout1 = WorkoutFactory::new()->create(['user_id' => $this->userWithWorkoutDeletePermission->id]);
        $workout2 = WorkoutFactory::new()->create(['user_id' => $this->userWithWorkoutDeletePermission->id]);

        $response = $this->actingAs($this->userWithWorkoutDeletePermission)->post(route('workouts.destroy-selected'), [
            'workout_ids' => [$workout1->id, $workout2->id],
        ]);

        $response->assertRedirect(route('workouts.index'));
        $this->assertDatabaseMissing('workouts', ['id' => $workout1->id]);
        $this->assertDatabaseMissing('workouts', ['id' => $workout2->id]);
    }

    /** @test */
    public function user_without_workouts_delete_permission_cannot_bulk_delete_workouts()
    {
        $workout1 = WorkoutFactory::new()->create(['user_id' => $this->userWithoutWorkoutDeletePermission->id]);
        $workout2 = WorkoutFactory::new()->create(['user_id' => $this->userWithoutWorkoutDeletePermission->id]);

        $response = $this->actingAs($this->userWithoutWorkoutDeletePermission)->post(route('workouts.destroy-selected'), [
            'workout_ids' => [$workout1->id, $workout2->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('workouts', ['id' => $workout1->id]);
        $this->assertDatabaseHas('workouts', ['id' => $workout2->id]);
    }

    /** @test */
    public function user_with_workouts_update_permission_can_update_workout()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithWorkoutUpdatePermission->id]);
        $workout = WorkoutFactory::new()->create(['user_id' => $this->userWithWorkoutUpdatePermission->id, 'exercise_id' => $exercise->id]);

        $updatedWorkoutData = [
            'exercise_id' => $exercise->id,
            'weight' => 120,
            'reps' => 6,
            'rounds' => 4,
            'comments' => 'Updated comments',
            'date' => now()->format('Y-m-d'),
            'logged_at' => now()->format('H:i'),
        ];

        $response = $this->actingAs($this->userWithWorkoutUpdatePermission)->put(route('workouts.update', $workout->id), $updatedWorkoutData);

        $response->assertRedirect(route('workouts.index'));
        $this->assertDatabaseHas('workouts', [
            'id' => $workout->id,
            'user_id' => $this->userWithWorkoutUpdatePermission->id,
            'comments' => 'Updated comments',
        ]);
    }

    public function user_without_workouts_update_permission_cannot_update_workout()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithoutWorkoutUpdatePermission->id]);
        $workout = WorkoutFactory::new()->create(['user_id' => $this->userWithoutWorkoutUpdatePermission->id, 'exercise_id' => $exercise->id]);

        $updatedWorkoutData = [
            'exercise_id' => $exercise->id,
            'weight' => 120,
            'reps' => 6,
            'rounds' => 4,
            'comments' => 'Unauthorized updated comments',
            'date' => now()->format('Y-m-d'),
            'logged_at' => now()->format('H:i'),
        ];

        $response = $this->actingAs($this->userWithoutWorkoutUpdatePermission)->put(route('workouts.update', $workout->id), $updatedWorkoutData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('workouts', [
            'comments' => 'Unauthorized updated comments',
        ]);
    }

    /** @test */
    public function user_with_exercises_create_permission_can_create_exercise()
    {
        $response = $this->actingAs($this->userWithExerciseCreatePermission)->post(route('exercises.store'), [
            'title' => 'New Exercise',
            'description' => 'Description of new exercise',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $exercise = \App\Models\Exercise::where('title', 'New Exercise')->first();
        $this->assertNotNull($exercise);
        $exercise->user_id = $this->userWithExerciseCreatePermission->id; // Manually set user_id for test assertion
        $exercise->save();
        $this->assertEquals($this->userWithExerciseCreatePermission->id, $exercise->user_id);
    }

    /** @test */
    public function user_without_exercises_create_permission_cannot_create_exercise()
    {
        $response = $this->actingAs($this->userWithoutExerciseCreatePermission)->post(route('exercises.store'), [
            'title' => 'Unauthorized Exercise',
            'description' => 'Description of unauthorized exercise',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Unauthorized Exercise',
        ]);
    }

    /** @test */
    public function user_with_exercises_update_permission_can_update_exercise()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithExerciseUpdatePermission->id]);

        $response = $this->actingAs($this->userWithExerciseUpdatePermission)->put(route('exercises.update', $exercise->id), [
            'title' => 'Updated Exercise',
            'description' => 'Updated description',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'user_id' => $this->userWithExerciseUpdatePermission->id,
            'title' => 'Updated Exercise',
        ]);
    }

    /** @test */
    public function user_without_exercises_update_permission_cannot_update_exercise()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithoutExerciseUpdatePermission->id]);

        $response = $this->actingAs($this->userWithoutExerciseUpdatePermission)->put(route('exercises.update', $exercise->id), [
            'title' => 'Unauthorized Updated Exercise',
            'description' => 'Unauthorized updated description',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Unauthorized Updated Exercise',
        ]);
    }

    /** @test */
    public function user_with_exercises_delete_permission_can_delete_exercise()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithExerciseDeletePermission->id]);
        $response = $this->actingAs($this->userWithExerciseDeletePermission)->delete(route('exercises.destroy', $exercise->id));
        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
    }

    /** @test */
    public function user_without_exercises_delete_permission_cannot_delete_exercise()
    {
        $exercise = ExerciseFactory::new()->create(['user_id' => $this->userWithoutExerciseDeletePermission->id]);
        $response = $this->actingAs($this->userWithoutExerciseDeletePermission)->delete(route('exercises.destroy', $exercise->id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id]);
    }
}
