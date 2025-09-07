<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;

class ExerciseManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /** @test */
    public function authenticated_user_can_create_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
        ]);
    }

    /** @test */
    public function authenticated_user_cannot_create_exercise_with_missing_title()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => '', // Missing title
            'description' => $this->faker->paragraph(),
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseMissing('exercises', [
            'user_id' => $user->id,
            'description' => $exerciseData['description'],
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_exercise()
    {
        $exerciseData = [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('exercises', $exerciseData);
    }

    /** @test */
    public function authenticated_user_can_create_bodyweight_exercise()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exerciseData = [
            'title' => 'Bodyweight Squat',
            'description' => 'A squat performed without external weight.',
            'is_bodyweight' => true,
        ];

        $response = $this->post(route('exercises.store'), $exerciseData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise created successfully.');
        $this->assertDatabaseHas('exercises', [
            'user_id' => $user->id,
            'title' => $exerciseData['title'],
            'description' => $exerciseData['description'],
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'is_bodyweight' => true,
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => true,
        ]);
    }

    /** @test */
    public function authenticated_user_can_edit_exercise_to_not_be_bodyweight()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        $updatedData = [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'is_bodyweight' => false,
        ];

        $response = $this->put(route('exercises.update', $exercise), $updatedData);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success', 'Exercise updated successfully.');
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => false,
        ]);
    }
}
