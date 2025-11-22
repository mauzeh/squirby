<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_bulk_delete_their_own_exercises()
    {
        $user = User::factory()->create();
        $exercises = Exercise::factory()->count(3)->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)->post(route('exercises.destroy-selected'), [
            'exercise_ids' => $exercises->pluck('id')->toArray()
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Selected exercises deleted successfully!');
        
        foreach ($exercises as $exercise) {
            $this->assertSoftDeleted($exercise);
        }
    }

    public function test_user_cannot_bulk_delete_other_users_exercises()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['user_id' => $user1->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $user2->id]);
        
        $response = $this->actingAs($user1)->post(route('exercises.destroy-selected'), [
            'exercise_ids' => [$exercise1->id, $exercise2->id]
        ]);

        $response->assertStatus(403);
        
        // Both exercises should still exist and not be soft deleted
        $this->assertDatabaseHas('exercises', ['id' => $exercise1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise2->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_requires_exercise_ids_array()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post(route('exercises.destroy-selected'), []);

        $response->assertSessionHasErrors(['exercise_ids']);
    }

    public function test_bulk_delete_validates_exercise_ids_exist()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->post(route('exercises.destroy-selected'), [
            'exercise_ids' => [999, 1000] // Non-existent IDs
        ]);

        $response->assertSessionHasErrors(['exercise_ids.0', 'exercise_ids.1']);
    }
}