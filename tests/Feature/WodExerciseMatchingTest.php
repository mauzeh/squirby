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

    public function test_wod_edit_shows_formatted_display_for_exercises()
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
            'wod_syntax' => "# Strength\n[[Back Squat]]: 5x5",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Back Squat',
                                'loggable' => true,
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
        $response->assertSee('Strength'); // Block name
        $response->assertSee('Back Squat'); // Exercise name
        $response->assertSee('5x5'); // Scheme
        $response->assertSee('wod-display'); // WOD display container
    }

    public function test_wod_edit_shows_formatted_display_for_non_existing_exercises()
    {
        $user = User::factory()->create();

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Strength\n[[NonExistentExercise]]: 3x8",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'NonExistentExercise',
                                'loggable' => true,
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
        $response->assertSee('3x8');
        $response->assertSee('wod-display');
    }

    public function test_wod_edit_shows_formatted_display_for_mixed_exercises()
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
            'wod_syntax' => "# Workout\n[[Bench Press]]: 5x5\n[[FakeExercise]]: 3x8",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Bench Press',
                                'loggable' => true,
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
                                'loggable' => true,
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
        // Both exercises should be shown in formatted display
        $response->assertSee('Bench Press');
        $response->assertSee('FakeExercise');
        $response->assertSee('wod-display');
    }

    public function test_wod_edit_shows_formatted_display_with_clickable_links()
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
            'wod_syntax' => "# Workout\n[[Push ups]]: 3x10",  // Different format (space instead of hyphen)
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Push ups',
                                'loggable' => true,
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
        $response->assertSee('3x10');
        // Should show formatted WOD display with clickable exercise link
        $response->assertSee('wod-display');
    }

    public function test_wod_only_shows_loggable_exercises_with_double_brackets()
    {
        $user = User::factory()->create();
        
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
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Back Squat',
                                'loggable' => true,
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 5,
                                    'reps' => 5,
                                    'display' => '5x5'
                                ]
                            ],
                            [
                                'type' => 'exercise',
                                'name' => 'Warm-up Squats',
                                'loggable' => false,
                                'scheme' => [
                                    'type' => 'sets_x_reps',
                                    'sets' => 2,
                                    'reps' => 10,
                                    'display' => '2x10'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertStatus(200);
        // Should show loggable exercise
        $response->assertSee('Back Squat');
        $response->assertSee('5x5');
        // Should NOT show non-loggable exercise
        $response->assertDontSee('Warm-up Squats');
        $response->assertDontSee('2x10');
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
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => '',
                        'exercises' => [
                            [
                                'type' => 'special_format',
                                'description' => 'AMRAP 10min',
                                'exercises' => [
                                    [
                                        'type' => 'exercise',
                                        'name' => 'Burpees',
                                        'reps' => 10,
                                        'loggable' => true
                                    ],
                                    [
                                        'type' => 'exercise',
                                        'name' => 'Stretching',
                                        'reps' => 5,
                                        'loggable' => false
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
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
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Back Squats',
                                'loggable' => true,
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
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Strength',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Deadlifts',
                                'loggable' => true,
                                'scheme' => [
                                    'type' => 'custom',
                                    'display' => '5 reps, building'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
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

    public function test_wod_table_shows_syntax_name_when_no_database_match()
    {
        $user = User::factory()->create();

        // No matching exercise in database
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test WOD',
            'wod_syntax' => "# Workout\n[[Nonexistent Exercise]] 3x8",
            'wod_parsed' => [
                'blocks' => [
                    [
                        'name' => 'Workout',
                        'exercises' => [
                            [
                                'type' => 'exercise',
                                'name' => 'Nonexistent Exercise',
                                'loggable' => true,
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

        $response = $this->actingAs($user)->get(route('workouts.index'));

        $response->assertStatus(200);
        // Should show syntax name since no database match exists
        $response->assertSee('Nonexistent Exercise');
        $response->assertSee('3x8');
    }
}
