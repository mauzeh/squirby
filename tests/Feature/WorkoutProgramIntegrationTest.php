<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutProgram;
use App\Models\ProgramExercise;
use Carbon\Carbon;

class WorkoutProgramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $exercises;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create a set of exercises for testing
        $this->exercises = [
            'squat' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Back Squat',
                'description' => 'Barbell back squat'
            ]),
            'bench' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Bench Press',
                'description' => 'Barbell bench press'
            ]),
            'deadlift' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Conventional Deadlift',
                'description' => 'Conventional deadlift from floor'
            ]),
            'ohp' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Overhead Press',
                'description' => 'Standing overhead press'
            ]),
            'rdl' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Romanian Deadlifts',
                'description' => 'Romanian deadlift variation'
            ]),
            'facepulls' => Exercise::factory()->create([
                'user_id' => $this->user->id,
                'title' => 'Face Pulls',
                'description' => 'Cable face pulls'
            ])
        ];
    }

    /** @test */
    public function complete_high_frequency_program_workflow()
    {
        $this->actingAs($this->user);
        
        // Step 1: Create Day 1 program (Heavy Squat & Bench)
        $day1Data = [
            'date' => '2025-09-15',
            'name' => 'Day 1 - Heavy Squat & Bench',
            'notes' => 'Focus on heavy compound movements',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['bench']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['rdl']->id,
                    'sets' => 3,
                    'reps' => 8,
                    'notes' => 'moderate weight',
                    'exercise_type' => 'accessory'
                ],
                [
                    'exercise_id' => $this->exercises['facepulls']->id,
                    'sets' => 3,
                    'reps' => 15,
                    'notes' => 'light weight, focus on form',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $day1Data);
        $response->assertRedirect(route('workout-programs.index', ['date' => '2025-09-15']));
        
        // Step 2: Create Day 2 program (Light Squat & Overhead Press)
        $day2Data = [
            'date' => '2025-09-16',
            'name' => 'Day 2 - Light Squat & Overhead Press',
            'notes' => 'Light squat day with overhead press focus',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 2,
                    'reps' => 5,
                    'notes' => '75-80% of Day 1 weight',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['ohp']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['rdl']->id,
                    'sets' => 3,
                    'reps' => 10,
                    'notes' => 'lighter than Day 1',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $day2Data);
        $response->assertRedirect(route('workout-programs.index', ['date' => '2025-09-16']));
        
        // Step 3: Create Day 3 program (Volume Squat & Deadlift)
        $day3Data = [
            'date' => '2025-09-17',
            'name' => 'Day 3 - Volume Squat & Deadlift',
            'notes' => 'High volume squat day with deadlifts',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 5,
                    'reps' => 5,
                    'notes' => '85-90% of Day 1 weight',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['deadlift']->id,
                    'sets' => 1,
                    'reps' => 5,
                    'notes' => 'heavy single set',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['facepulls']->id,
                    'sets' => 3,
                    'reps' => 20,
                    'notes' => 'high rep for recovery',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $day3Data);
        $response->assertRedirect(route('workout-programs.index', ['date' => '2025-09-17']));
        
        // Step 4: Verify all programs were created correctly
        $this->assertEquals(3, WorkoutProgram::where('user_id', $this->user->id)->count());
        
        // Step 5: Test navigation between days
        $day1Response = $this->get(route('workout-programs.index', ['date' => '2025-09-15']));
        $day1Response->assertSee('Day 1 - Heavy Squat & Bench');
        $day1Response->assertSee('Back Squat');
        $day1Response->assertSee('3 sets × 5 reps');
        
        $day2Response = $this->get(route('workout-programs.index', ['date' => '2025-09-16']));
        $day2Response->assertSee('Day 2 - Light Squat & Overhead Press');
        $day2Response->assertSee('75-80% of Day 1 weight');
        
        $day3Response = $this->get(route('workout-programs.index', ['date' => '2025-09-17']));
        $day3Response->assertSee('Day 3 - Volume Squat & Deadlift');
        $day3Response->assertSee('5 sets × 5 reps');
        
        // Step 6: Test editing a program
        $day1Program = WorkoutProgram::forDate('2025-09-15')->where('user_id', $this->user->id)->first();
        
        $editResponse = $this->get(route('workout-programs.edit', $day1Program));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Day 1 - Heavy Squat & Bench');
        
        // Step 7: Update the program
        $updateData = [
            'date' => '2025-09-15',
            'name' => 'Day 1 - Heavy Squat & Bench (Updated)',
            'notes' => 'Updated notes with form cues',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 4, // Changed from 3
                    'reps' => 5,
                    'notes' => 'heavy - focus on depth',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['bench']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy - pause bench',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['rdl']->id,
                    'sets' => 3,
                    'reps' => 8,
                    'notes' => 'moderate weight',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $updateResponse = $this->withoutMiddleware()
            ->put(route('workout-programs.update', $day1Program), $updateData);
        $updateResponse->assertRedirect(route('workout-programs.index', ['date' => '2025-09-15']));
        
        // Verify the update
        $day1Program->refresh();
        $this->assertEquals('Day 1 - Heavy Squat & Bench (Updated)', $day1Program->name);
        $this->assertEquals('Updated notes with form cues', $day1Program->notes);
        
        $squatExercise = $day1Program->exercises()->where('title', 'Back Squat')->first();
        $this->assertEquals(4, $squatExercise->pivot->sets);
        $this->assertEquals('heavy - focus on depth', $squatExercise->pivot->notes);
        
        // Step 8: Test program deletion
        $deleteResponse = $this->withoutMiddleware()
            ->delete(route('workout-programs.destroy', $day1Program));
        $deleteResponse->assertRedirect(route('workout-programs.index', ['date' => '2025-09-15']));
        
        $this->assertDatabaseMissing('workout_programs', ['id' => $day1Program->id]);
        $this->assertEquals(2, WorkoutProgram::where('user_id', $this->user->id)->count());
    }

    /** @test */
    public function multi_user_program_isolation_workflow()
    {
        $user2 = User::factory()->create();
        
        // Create exercises for user2
        $user2Exercise = Exercise::factory()->create([
            'user_id' => $user2->id,
            'title' => 'User 2 Exercise'
        ]);
        
        // User 1 creates a program
        $this->actingAs($this->user);
        
        $user1ProgramData = [
            'date' => '2025-09-15',
            'name' => 'User 1 Program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'exercise_type' => 'main'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $user1ProgramData);
        $response->assertRedirect();
        
        // User 2 creates a program on the same date
        $this->actingAs($user2);
        
        $user2ProgramData = [
            'date' => '2025-09-15',
            'name' => 'User 2 Program',
            'exercises' => [
                [
                    'exercise_id' => $user2Exercise->id,
                    'sets' => 4,
                    'reps' => 6,
                    'exercise_type' => 'main'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $user2ProgramData);
        $response->assertRedirect();
        
        // Verify user isolation
        $user1Programs = WorkoutProgram::where('user_id', $this->user->id)->get();
        $user2Programs = WorkoutProgram::where('user_id', $user2->id)->get();
        
        $this->assertCount(1, $user1Programs);
        $this->assertCount(1, $user2Programs);
        
        // User 1 should only see their program
        $this->actingAs($this->user);
        $response = $this->get(route('workout-programs.index', ['date' => '2025-09-15']));
        $response->assertSee('User 1 Program');
        $response->assertDontSee('User 2 Program');
        
        // User 2 should only see their program
        $this->actingAs($user2);
        $response = $this->get(route('workout-programs.index', ['date' => '2025-09-15']));
        $response->assertSee('User 2 Program');
        $response->assertDontSee('User 1 Program');
        
        // User 1 cannot access User 2's program
        $user2Program = $user2Programs->first();
        $this->actingAs($this->user);
        
        $response = $this->get(route('workout-programs.edit', $user2Program));
        $response->assertStatus(403);
        
        $response = $this->withoutMiddleware()
            ->put(route('workout-programs.update', $user2Program), []);
        $response->assertStatus(403);
        
        $response = $this->withoutMiddleware()
            ->delete(route('workout-programs.destroy', $user2Program));
        $response->assertStatus(403);
    }

    /** @test */
    public function date_range_program_management_workflow()
    {
        $this->actingAs($this->user);
        
        // Create programs for a week
        $dates = ['2025-09-15', '2025-09-16', '2025-09-17', '2025-09-18', '2025-09-19'];
        
        foreach ($dates as $index => $date) {
            $programData = [
                'date' => $date,
                'name' => "Day " . ($index + 1) . " Program",
                'notes' => "Notes for day " . ($index + 1),
                'exercises' => [
                    [
                        'exercise_id' => $this->exercises['squat']->id,
                        'sets' => 3 + $index,
                        'reps' => 5,
                        'notes' => "Day " . ($index + 1) . " intensity",
                        'exercise_type' => 'main'
                    ]
                ]
            ];

            $response = $this->withoutMiddleware()
                ->post(route('workout-programs.store'), $programData);
            $response->assertRedirect();
        }
        
        // Verify all programs were created
        $this->assertEquals(5, WorkoutProgram::where('user_id', $this->user->id)->count());
        
        // Test date filtering
        foreach ($dates as $index => $date) {
            $response = $this->get(route('workout-programs.index', ['date' => $date]));
            $response->assertSee("Day " . ($index + 1) . " Program");
            
            // Should not see other days' programs
            for ($i = 0; $i < count($dates); $i++) {
                if ($i !== $index) {
                    $response->assertDontSee("Day " . ($i + 1) . " Program");
                }
            }
        }
        
        // Test date range queries using model scopes
        $weekPrograms = WorkoutProgram::forDateRange('2025-09-15', '2025-09-19')
            ->where('user_id', $this->user->id)
            ->get();
        
        $this->assertCount(5, $weekPrograms);
        
        $partialWeekPrograms = WorkoutProgram::forDateRange('2025-09-15', '2025-09-17')
            ->where('user_id', $this->user->id)
            ->get();
        
        $this->assertCount(3, $partialWeekPrograms);
    }

    /** @test */
    public function complex_program_modification_workflow()
    {
        $this->actingAs($this->user);
        
        // Create initial complex program
        $initialData = [
            'date' => '2025-09-15',
            'name' => 'Complex Program',
            'notes' => 'Initial complex program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['bench']->id,
                    'sets' => 3,
                    'reps' => 8,
                    'notes' => 'moderate',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['rdl']->id,
                    'sets' => 3,
                    'reps' => 10,
                    'notes' => 'light',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->post(route('workout-programs.store'), $initialData);
        $response->assertRedirect();
        
        $program = WorkoutProgram::where('user_id', $this->user->id)->first();
        
        // Modification 1: Add exercises
        $modificationData1 = [
            'date' => '2025-09-15',
            'name' => 'Complex Program - Modified',
            'notes' => 'Added more exercises',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['squat']->id,
                    'sets' => 3,
                    'reps' => 5,
                    'notes' => 'heavy',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['bench']->id,
                    'sets' => 3,
                    'reps' => 8,
                    'notes' => 'moderate',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['deadlift']->id, // New exercise
                    'sets' => 1,
                    'reps' => 5,
                    'notes' => 'heavy single',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['rdl']->id,
                    'sets' => 3,
                    'reps' => 10,
                    'notes' => 'light',
                    'exercise_type' => 'accessory'
                ],
                [
                    'exercise_id' => $this->exercises['facepulls']->id, // New exercise
                    'sets' => 3,
                    'reps' => 15,
                    'notes' => 'light',
                    'exercise_type' => 'accessory'
                ]
            ]
        ];

        $response = $this->withoutMiddleware()
            ->put(route('workout-programs.update', $program), $modificationData1);
        $response->assertRedirect();
        
        $program->refresh();
        $this->assertCount(5, $program->exercises);
        
        // Modification 2: Reorder and remove exercises
        $modificationData2 = [
            'date' => '2025-09-15',
            'name' => 'Complex Program - Simplified',
            'notes' => 'Simplified program',
            'exercises' => [
                [
                    'exercise_id' => $this->exercises['deadlift']->id, // Now first
                    'sets' => 1,
                    'reps' => 5,
                    'notes' => 'heavy single',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['squat']->id, // Now second
                    'sets' => 5, // Changed sets
                    'reps' => 5,
                    'notes' => 'volume work',
                    'exercise_type' => 'main'
                ],
                [
                    'exercise_id' => $this->exercises['facepulls']->id, // Only accessory
                    'sets' => 4, // Changed sets
                    'reps' => 20, // Changed reps
                    'notes' => 'high volume',
                    'exercise_type' => 'accessory'
                ]
                // Removed bench and RDL
            ]
        ];

        $response = $this->withoutMiddleware()
            ->put(route('workout-programs.update', $program), $modificationData2);
        $response->assertRedirect();
        
        $program->refresh();
        $this->assertCount(3, $program->exercises);
        
        // Verify new ordering
        $exercises = $program->exercises()->orderByPivot('exercise_order')->get();
        $this->assertEquals('Conventional Deadlift', $exercises[0]->title);
        $this->assertEquals(1, $exercises[0]->pivot->exercise_order);
        $this->assertEquals('Back Squat', $exercises[1]->title);
        $this->assertEquals(2, $exercises[1]->pivot->exercise_order);
        $this->assertEquals('Face Pulls', $exercises[2]->title);
        $this->assertEquals(3, $exercises[2]->pivot->exercise_order);
        
        // Verify parameter changes
        $this->assertEquals(5, $exercises[1]->pivot->sets); // Squat sets changed
        $this->assertEquals(20, $exercises[2]->pivot->reps); // Face pulls reps changed
    }
}