<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Workout;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WodExerciseMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_wod_edit_shows_link_for_existing_exercises()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Back Squat',
            'exercise_type' => 'weighted_resistance',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\nBack Squat: 5x5",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Back Squat',
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 5,
                                    'reps' => 5,
                                    'display' => '5x5'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('Back Squat');
        $response->assertSee('fa-link');
        $response->assertSee(route('exercises.edit', $exercise->id));
    }

    public function test_wod_edit_shows_warning_for_non_existing_exercises()
    {
        $user = User::factory()->create();

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\nNonExistentExercise: 3x8",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'NonExistentExercise',
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 3,
                                    'reps' => 8,
                                    'display' => '3x8'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('NonExistentExercise');
        $response->assertSee('fa-exclamation-triangle');
        $response->assertSee('Exercise not found in your library');
    }

    public function test_wod_edit_shows_mixed_existing_and_non_existing_exercises()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Bench Press',
            'exercise_type' => 'weighted_resistance',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\nBench Press: 5x5\nFakeExercise: 3x8",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Bench Press',
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 5,
                                    'reps' => 5,
                                    'display' => '5x5'
                                ]
                            ],
                            [
                                'type' => 'exercise',
                                'name' => 'FakeExercise',
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 3,
                                    'reps' => 8,
                                    'display' => '3x8'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        // Existing exercise should have link
        $response->assertSee('Bench Press');
        $response->assertSee(route('exercises.edit', $exercise->id));
        // Non-existing exercise should have warning
        $response->assertSee('FakeExercise');
        $response->assertSee('fa-exclamation-triangle');
    }

    public function test_wod_edit_uses_fuzzy_matching_for_exercises()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight',
        ]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\nPush ups: 3x10",  // Different format (space instead of hyphen)
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Push ups',
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 3,
                                    'reps' => 10,
                                    'display' => '3x10'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.edit', $workout));

        $response->assertStatus(200);
        $response->assertSee('Push ups');
        // Should match "Push-ups" exercise via fuzzy matching
        $response->assertSee('fa-link');
        $response->assertSee(route('exercises.edit', $exercise->id));
    }
}
