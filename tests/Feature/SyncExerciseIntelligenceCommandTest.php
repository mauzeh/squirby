<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class SyncExerciseIntelligenceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test directory if it doesn't exist
        if (!File::exists(database_path('imports'))) {
            File::makeDirectory(database_path('imports'), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testFiles = [
            database_path('imports/test_intelligence.json'),
            database_path('imports/invalid_json.json'),
            database_path('imports/empty_intelligence.json'),
        ];

        foreach ($testFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_syncs_intelligence_data_with_default_file()
    {
        // Create a global exercise
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'user_id' => null
        ]);

        // Create test intelligence data file
        $intelligenceData = [
            'bench_press' => [
                'canonical_name' => 'bench_press',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'pectoralis_major',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48
            ]
        ];

        // Create the default intelligence file
        File::put(
            database_path('seeders/json/exercise_intelligence_data.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence')
            ->expectsOutput('Starting synchronization of exercise intelligence data...')
            ->expectsOutput('Synchronized intelligence for: bench_press')
            ->expectsOutput('Exercise intelligence synchronization completed.')
            ->assertExitCode(0);

        // Assert intelligence was created
        $this->assertDatabaseHas('exercise_intelligence', [
            'exercise_id' => $exercise->id,
            'canonical_name' => 'bench_press',
            'primary_mover' => 'pectoralis_major',
            'movement_archetype' => 'push'
        ]);

        // Clean up
        File::delete(database_path('seeders/json/exercise_intelligence_data.json'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_syncs_intelligence_data_with_custom_file()
    {
        // Create a global exercise
        $exercise = Exercise::factory()->create([
            'title' => 'Squat',
            'canonical_name' => 'squat',
            'user_id' => null
        ]);

        // Create test intelligence data
        $intelligenceData = [
            'squat' => [
                'canonical_name' => 'squat',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'rectus_femoris',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 72
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', ['--file' => 'test_intelligence.json'])
            ->expectsOutput('Starting synchronization of exercise intelligence data...')
            ->expectsOutput('Synchronized intelligence for: squat')
            ->expectsOutput('Exercise intelligence synchronization completed.')
            ->assertExitCode(0);

        // Assert intelligence was created
        $this->assertDatabaseHas('exercise_intelligence', [
            'exercise_id' => $exercise->id,
            'canonical_name' => 'squat',
            'primary_mover' => 'rectus_femoris',
            'movement_archetype' => 'squat'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_intelligence_data()
    {
        // Create a global exercise with existing intelligence
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'deadlift',
            'user_id' => null
        ]);

        $existingIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'canonical_name' => 'deadlift',
            'primary_mover' => 'old_muscle',
            'difficulty_level' => 3
        ]);

        // Create updated intelligence data
        $intelligenceData = [
            'deadlift' => [
                'canonical_name' => 'deadlift',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'gluteus_maximus',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'gluteus_maximus',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'hinge',
                'category' => 'strength',
                'difficulty_level' => 5,
                'recovery_hours' => 72
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', ['--file' => 'test_intelligence.json'])
            ->expectsOutput('Synchronized intelligence for: deadlift')
            ->assertExitCode(0);

        // Assert intelligence was updated
        $existingIntelligence->refresh();
        $this->assertEquals('gluteus_maximus', $existingIntelligence->primary_mover);
        $this->assertEquals(5, $existingIntelligence->difficulty_level);
        $this->assertEquals('hinge', $existingIntelligence->movement_archetype);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_runs_in_dry_run_mode_without_making_changes()
    {
        // Create a global exercise
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'push_up',
            'user_id' => null
        ]);

        // Create test intelligence data
        $intelligenceData = [
            'push_up' => [
                'canonical_name' => 'push_up',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'pectoralis_major',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 2,
                'recovery_hours' => 24
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', [
            '--file' => 'test_intelligence.json',
            '--dry-run' => true
        ])
            ->expectsOutput('DRY RUN MODE - No changes will be made to the database')
            ->expectsOutput("[DRY RUN] Would CREATE intelligence for: push_up (Exercise ID: {$exercise->id})")
            ->expectsOutput('DRY RUN completed - No changes were made to the database.')
            ->assertExitCode(0);

        // Assert no intelligence was created
        $this->assertDatabaseMissing('exercise_intelligence', [
            'exercise_id' => $exercise->id
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_update_message_in_dry_run_mode_for_existing_intelligence()
    {
        // Create a global exercise with existing intelligence
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'pull_up',
            'user_id' => null
        ]);

        ExerciseIntelligence::factory()->create([
            'exercise_id' => $exercise->id,
            'canonical_name' => 'pull_up'
        ]);

        // Create test intelligence data
        $intelligenceData = [
            'pull_up' => [
                'canonical_name' => 'pull_up',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'latissimus_dorsi',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'latissimus_dorsi',
                'largest_muscle' => 'latissimus_dorsi',
                'movement_archetype' => 'pull',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 48
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', [
            '--file' => 'test_intelligence.json',
            '--dry-run' => true
        ])
            ->expectsOutput("[DRY RUN] Would UPDATE intelligence for: pull_up (Exercise ID: {$exercise->id})")
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_user_exercises()
    {
        $user = User::factory()->create();
        
        // Create a user exercise (not global)
        $userExercise = Exercise::factory()->create([
            'canonical_name' => 'user_exercise',
            'user_id' => $user->id
        ]);

        // Create test intelligence data
        $intelligenceData = [
            'user_exercise' => [
                'canonical_name' => 'user_exercise',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'some_muscle',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'some_muscle',
                'largest_muscle' => 'some_muscle',
                'movement_archetype' => 'unknown',
                'category' => 'strength',
                'difficulty_level' => 1,
                'recovery_hours' => 24
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', ['--file' => 'test_intelligence.json'])
            ->expectsOutput('Exercise not found or not global: user_exercise. Skipping.')
            ->assertExitCode(0);

        // Assert no intelligence was created
        $this->assertDatabaseMissing('exercise_intelligence', [
            'exercise_id' => $userExercise->id
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_to_title_lookup_when_canonical_name_fails()
    {
        // Create exercise without canonical_name match but with title match
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'different_canonical_name',
            'user_id' => null
        ]);

        // Create intelligence data with key matching title but different canonical_name
        $intelligenceData = [
            'Bench Press' => [
                'canonical_name' => 'bench_press', // This won't match the exercise
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'pectoralis_major',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', ['--file' => 'test_intelligence.json'])
            ->expectsOutput('Synchronized intelligence for: bench_press')
            ->assertExitCode(0);

        // Assert intelligence was created using title fallback
        $this->assertDatabaseHas('exercise_intelligence', [
            'exercise_id' => $exercise->id,
            'primary_mover' => 'pectoralis_major'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_missing_file_gracefully()
    {
        $this->artisan('exercises:sync-intelligence', ['--file' => 'nonexistent.json'])
            ->expectsOutput('Exercise intelligence JSON file not found at: ' . database_path('imports/nonexistent.json'))
            ->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_invalid_json_gracefully()
    {
        // Create invalid JSON file
        File::put(database_path('imports/invalid_json.json'), '{ invalid json }');

        $this->artisan('exercises:sync-intelligence', ['--file' => 'invalid_json.json'])
            ->expectsOutputToContain('Error decoding JSON file:')
            ->assertExitCode(1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_json_data()
    {
        // Create empty JSON file
        File::put(database_path('imports/empty_intelligence.json'), '{}');

        $this->artisan('exercises:sync-intelligence', ['--file' => 'empty_intelligence.json'])
            ->expectsOutput('Starting synchronization of exercise intelligence data...')
            ->expectsOutput('Exercise intelligence synchronization completed.')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_multiple_exercises_correctly()
    {
        // Create multiple global exercises
        $exercise1 = Exercise::factory()->create([
            'canonical_name' => 'bench_press',
            'user_id' => null
        ]);
        
        $exercise2 = Exercise::factory()->create([
            'canonical_name' => 'squat',
            'user_id' => null
        ]);

        // Create intelligence data for multiple exercises
        $intelligenceData = [
            'bench_press' => [
                'canonical_name' => 'bench_press',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'pectoralis_major',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'pectoralis_major',
                'largest_muscle' => 'pectoralis_major',
                'movement_archetype' => 'push',
                'category' => 'strength',
                'difficulty_level' => 3,
                'recovery_hours' => 48
            ],
            'squat' => [
                'canonical_name' => 'squat',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'rectus_femoris',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'rectus_femoris',
                'largest_muscle' => 'gluteus_maximus',
                'movement_archetype' => 'squat',
                'category' => 'strength',
                'difficulty_level' => 4,
                'recovery_hours' => 72
            ],
            'nonexistent_exercise' => [
                'canonical_name' => 'nonexistent_exercise',
                'muscle_data' => [
                    'muscles' => [
                        [
                            'name' => 'some_muscle',
                            'role' => 'primary_mover',
                            'contraction_type' => 'isotonic'
                        ]
                    ]
                ],
                'primary_mover' => 'some_muscle',
                'largest_muscle' => 'some_muscle',
                'movement_archetype' => 'unknown',
                'category' => 'strength',
                'difficulty_level' => 1,
                'recovery_hours' => 24
            ]
        ];

        File::put(
            database_path('imports/test_intelligence.json'),
            json_encode($intelligenceData, JSON_PRETTY_PRINT)
        );

        $this->artisan('exercises:sync-intelligence', ['--file' => 'test_intelligence.json'])
            ->expectsOutput('Synchronized intelligence for: bench_press')
            ->expectsOutput('Synchronized intelligence for: squat')
            ->expectsOutput('Exercise not found or not global: nonexistent_exercise. Skipping.')
            ->assertExitCode(0);

        // Assert both exercises got intelligence data
        $this->assertDatabaseHas('exercise_intelligence', [
            'exercise_id' => $exercise1->id,
            'primary_mover' => 'pectoralis_major'
        ]);

        $this->assertDatabaseHas('exercise_intelligence', [
            'exercise_id' => $exercise2->id,
            'primary_mover' => 'rectus_femoris'
        ]);
    }
}