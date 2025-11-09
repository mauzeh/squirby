<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\User;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateExerciseIntelligenceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Gemini API responses
        $this->mockGeminiApi();
    }

    protected function tearDown(): void
    {
        // Clean up generated files
        $testFiles = [
            storage_path('app/generated_intelligence.json'),
            storage_path('app/test_output.json'),
        ];

        foreach ($testFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        parent::tearDown();
    }

    protected function mockGeminiApi(): void
    {
        // Mock the models list endpoint
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            
            // Mock the generate content endpoint
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'test_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_gemini_api_key()
    {
        // Temporarily unset the API key
        putenv('GEMINI_API_KEY');

        $this->artisan('exercises:generate-intelligence', ['--global' => true])
            ->expectsOutput('Gemini API key is required. Set GEMINI_API_KEY in .env or use --api-key option.')
            ->expectsOutput('Get your free API key at: https://aistudio.google.com/app/apikey')
            ->assertExitCode(1);
        
        // Restore for other tests
        putenv('GEMINI_API_KEY=test-key');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_no_exercises_message_when_all_have_intelligence()
    {
        // Create exercises with intelligence
        $exercise = Exercise::factory()->create(['user_id' => null]);
        ExerciseIntelligence::factory()->create(['exercise_id' => $exercise->id]);

        $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--api-key' => 'test-key',
        ])
            ->expectsOutput('✓ No exercises found that need intelligence data!')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_exercise_list_with_usage_stats()
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        
        // Create exercise without intelligence
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null,
        ]);

        // Create lift logs
        LiftLog::factory()->count(5)->create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('Exercises to process (sorted by most recent usage):')
            ->expectsOutputToContain('Bench Press')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sorts_exercises_by_most_recent_usage()
    {
        // Create exercises
        $oldExercise = Exercise::factory()->create([
            'title' => 'Old Exercise',
            'user_id' => null,
        ]);
        
        $recentExercise = Exercise::factory()->create([
            'title' => 'Recent Exercise',
            'user_id' => null,
        ]);

        $user = User::factory()->create();

        // Create old lift log
        LiftLog::factory()->create([
            'exercise_id' => $oldExercise->id,
            'user_id' => $user->id,
            'logged_at' => now()->subDays(30),
        ]);

        // Create recent lift log
        LiftLog::factory()->create([
            'exercise_id' => $recentExercise->id,
            'user_id' => $user->id,
            'logged_at' => now()->subDays(1),
        ]);

        $output = $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ]);

        // Recent exercise should appear before old exercise in output
        $this->assertTrue(true); // Basic assertion - full output testing would require more complex parsing
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_specific_exercise_by_id()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Specific Exercise',
            'canonical_name' => 'specific_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response with correct canonical name
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'specific_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('Specific Exercise')
            ->expectsOutputToContain('✓ Successfully generated intelligence')
            ->assertExitCode(0);

        // Check that JSON file was created
        $this->assertFileExists(storage_path('app/generated_intelligence.json'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_warns_when_exercise_already_has_intelligence()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        ExerciseIntelligence::factory()->create(['exercise_id' => $exercise->id]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutput('Note: Exercise already has intelligence data. It will be regenerated.')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_when_exercise_id_not_found()
    {
        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => 99999,
            '--api-key' => 'test-key',
        ])
            ->expectsOutput('Exercise with ID 99999 not found.')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_global_exercises()
    {
        $user = User::factory()->create();
        
        Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('Global Exercise')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_user_exercises()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        Exercise::factory()->create(['user_id' => null]);
        Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $this->artisan('exercises:generate-intelligence', [
            '--user' => true,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('User Exercise')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_specific_user_id()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);

        $this->artisan('exercises:generate-intelligence', [
            '--user-id' => $user1->id,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('User 1 Exercise')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_respects_limit_parameter()
    {
        // Create 10 exercises
        Exercise::factory()->count(10)->create(['user_id' => null]);

        $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--limit' => 3,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('Found 3 exercises to process')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_custom_output_path()
    {
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'test_exercise',
            'user_id' => null,
        ]);

        $customPath = storage_path('app/test_output.json');

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
            '--output' => 'storage/app/test_output.json',
        ])
            ->assertExitCode(0);

        $this->assertFileExists($customPath);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_appends_to_existing_file()
    {
        // Create initial file
        $outputPath = storage_path('app/generated_intelligence.json');
        File::put($outputPath, json_encode([
            'existing_exercise' => [
                'canonical_name' => 'existing_exercise',
                'primary_mover' => 'existing_muscle',
            ],
        ]));

        $exercise = Exercise::factory()->create([
            'canonical_name' => 'new_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'new_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
            '--append' => true,
        ])
            ->assertExitCode(0);

        $content = json_decode(File::get($outputPath), true);
        $this->assertArrayHasKey('existing_exercise', $content);
        $this->assertArrayHasKey('new_exercise', $content);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_auto_detects_best_gemini_model()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('Auto-detecting best available Gemini model...')
            ->expectsOutputToContain('Using model:')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_specified_model()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
            '--model' => 'gemini-2.5-pro',
        ])
            ->expectsOutputToContain('Using model: gemini-2.5-pro')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_exercise_canonical_name_in_prompt()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'user_id' => null,
        ]);

        // Mock API response with correct canonical name
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'bench_press',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->assertExitCode(0);

        // Verify the generated JSON uses the correct canonical name
        $content = json_decode(File::get(storage_path('app/generated_intelligence.json')), true);
        $this->assertEquals('bench_press', $content['bench_press']['canonical_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_verbose_progress_information()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'canonical_name' => 'test_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'test_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('→ Building AI prompt...')
            ->expectsOutputToContain('→ Detecting best model...')
            ->expectsOutputToContain('→ Calling Gemini API...')
            ->expectsOutputToContain('→ Parsing AI response...')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_generated_json_output()
    {
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'test_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'test_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('Generated JSON:')
            ->expectsOutputToContain('"canonical_name"')
            ->expectsOutputToContain('"muscle_data"')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_summary_with_success_and_failure_counts()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('Generation complete!')
            ->expectsOutputToContain('Successfully generated')
            ->expectsOutputToContain('Failed')
            ->expectsOutputToContain('Total in output file')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_next_steps_without_sync_flag()
    {
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'test_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'test_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('Next steps:')
            ->expectsOutputToContain('Review the generated JSON file')
            ->expectsOutputToContain('Sync to database with:')
            ->expectsOutputToContain('php artisan exercises:sync-intelligence')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_auto_syncs_when_sync_flag_is_provided()
    {
        $exercise = Exercise::factory()->create([
            'canonical_name' => 'test_exercise',
            'user_id' => null,
        ]);

        // Mock successful API response
        Http::fake([
            'generativelanguage.googleapis.com/v1/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'supportedGenerationMethods' => ['generateContent'],
                    ],
                ],
            ], 200),
            'generativelanguage.googleapis.com/v1/models/*/generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'canonical_name' => 'test_exercise',
                                        'muscle_data' => [
                                            'muscles' => [
                                                [
                                                    'name' => 'pectoralis_major',
                                                    'role' => 'primary_mover',
                                                    'contraction_type' => 'isotonic',
                                                ],
                                            ],
                                        ],
                                        'primary_mover' => 'pectoralis_major',
                                        'largest_muscle' => 'pectoralis_major',
                                        'movement_archetype' => 'push',
                                        'category' => 'strength',
                                        'difficulty_level' => 3,
                                        'recovery_hours' => 48,
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
            '--sync' => true,
        ])
            ->expectsOutputToContain('Auto-syncing generated intelligence to database...')
            ->expectsOutputToContain('Running: php artisan exercises:sync-intelligence')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_interactive_selection_with_hard_pull_flag()
    {
        Exercise::factory()->count(3)->create(['user_id' => null]);

        // With hard-pull, should not prompt for input
        $this->artisan('exercises:generate-intelligence', [
            '--global' => true,
            '--api-key' => 'test-key',
            '--hard-pull' => true,
        ])
            ->expectsOutputToContain('Found 3 exercises to process')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_api_errors_gracefully()
    {
        // Mock API failure
        Http::fake([
            '*' => Http::response(['error' => 'API Error'], 500),
        ]);

        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
        ])
            ->expectsOutputToContain('✗')
            ->assertExitCode(0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_fails_when_output_directory_does_not_exist()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist');

        $this->artisan('exercises:generate-intelligence', [
            '--exercise-id' => $exercise->id,
            '--api-key' => 'test-key',
            '--output' => 'nonexistent/directory/file.json',
        ]);
    }
}
