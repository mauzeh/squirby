<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\ImportJsonLiftLog;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ImportJsonLiftLogTest extends TestCase
{
    use RefreshDatabase;

    private function getCommand()
    {
        return new ImportJsonLiftLog();
    }

    private function callPrivateMethod($object, $method, $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    private function createTestUser(): User
    {
        return User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);
    }

    private function createGlobalExercise(string $title, string $canonicalName): Exercise
    {
        return Exercise::create([
            'title' => $title,
            'canonical_name' => $canonicalName,
            'description' => 'Test exercise',
            'user_id' => null, // Global exercise
            'exercise_type' => 'regular'
        ]);
    }

    private function createTestJsonFile(array $exercises): string
    {
        // Convert old format to new format if needed
        $formattedExercises = [];
        
        foreach ($exercises as $exercise) {
            if (isset($exercise['lift_logs'])) {
                // Already in new format
                $formattedExercises[] = $exercise;
            } else {
                // Convert from old format to new format
                $formattedExercises[] = [
                    'exercise' => $exercise['exercise'],
                    'canonical_name' => $exercise['canonical_name'],
                    'description' => $exercise['description'] ?? 'Test exercise',
                    'is_bodyweight' => $exercise['is_bodyweight'] ?? false,
                    'lift_logs' => [
                        [
                            'weight' => $exercise['weight'] ?? 0,
                            'reps' => $exercise['reps'] ?? 1,
                            'sets' => $exercise['sets'] ?? 1,
                            'notes' => $exercise['notes'] ?? null
                        ]
                    ]
                ];
            }
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, json_encode($formattedExercises));
        return $tempFile;
    }

    public function test_handle_returns_failure_when_file_not_found()
    {
        $command = $this->getCommand();
        
        $this->artisan('lift-log:import-json', [
            'file' => 'nonexistent.json',
            '--user-email' => 'test@example.com'
        ])->assertExitCode(Command::FAILURE);
    }

    public function test_handle_returns_failure_when_user_not_found()
    {
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'nonexistent@example.com'
            ])->assertExitCode(Command::FAILURE);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_handle_returns_failure_when_json_is_invalid()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, 'invalid json content');
        
        try {
            $this->createTestUser();
            
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com'
            ])->assertExitCode(Command::FAILURE);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_handle_returns_success_when_no_exercises_found()
    {
        $tempFile = $this->createTestJsonFile([]);
        
        try {
            $this->createTestUser();
            
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com'
            ])->assertExitCode(Command::SUCCESS);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_import_exercise_with_existing_global_exercise()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 225,
                    'reps' => 5,
                    'sets' => 1,
                    'notes' => null,
            'exercise_type' => 'regular'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift log was created
        $this->assertDatabaseHas('lift_logs', [
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'comments' => null
        ]);
        
        // Verify lift set was created
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
    }

    public function test_import_exercise_with_multiple_sets()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 225,
                    'reps' => 5,
                    'sets' => 3,
                    'notes' => null,
            'exercise_type' => 'regular'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify 3 lift sets were created
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $liftSets = LiftSet::where('lift_log_id', $liftLog->id)->get();
        
        $this->assertCount(3, $liftSets);
        
        foreach ($liftSets as $liftSet) {
            $this->assertEquals(225, $liftSet->weight);
            $this->assertEquals(5, $liftSet->reps);
        }
    }

    public function test_import_exercise_with_notes()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Plank', 'plank');
        
        $exerciseData = [
            'exercise' => 'Plank',
            'canonical_name' => 'plank',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 0,
                    'reps' => 90,
                    'sets' => 1,
                    'notes' => 'time in seconds',
            'exercise_type' => 'bodyweight'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift set was created (notes are stored in lift_log comments, not lift_sets)
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 90
        ]);
        
        // Verify notes are stored in lift_log comments
        $this->assertEquals('time in seconds', $liftLog->comments);
    }

    public function test_find_or_create_exercise_finds_existing_global_exercise()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'exercise_type' => 'regular'
        ];
        
        $command = $this->getCommand();
        $result = $this->callPrivateMethod($command, 'findOrCreateExercise', [$exerciseData, $user]);
        
        $this->assertEquals($exercise->id, $result['exercise']->id);
    }

    public function test_create_new_user_exercise()
    {
        $user = User::factory()->create();
        
        $exerciseData = [
            'exercise' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'exercise_type' => 'bodyweight'
        ];
        
        $command = $this->getCommand();
        $result = $this->callPrivateMethod($command, 'createNewUserExercise', [$exerciseData, $user]);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'user_id' => $user->id,
            'description' => 'Imported from JSON file',
            'exercise_type' => 'bodyweight'
        ]);
        
        $this->assertEquals('New Exercise', $result->title);
        $this->assertEquals('new_exercise', $result->canonical_name);
        $this->assertEquals('bodyweight', $result->exercise_type);
        $this->assertEquals($user->id, $result->user_id);
    }

    public function test_handle_uses_provided_date()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])->assertExitCode(Command::SUCCESS);
            
            $this->assertDatabaseHas('lift_logs', [
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'logged_at' => '2024-01-15 00:00:00'
            ]);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_handle_uses_current_date_when_not_provided()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $now = Carbon::now();
            Carbon::setTestNow($now);
            
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com'
            ])->assertExitCode(Command::SUCCESS);
            
            $this->assertDatabaseHas('lift_logs', [
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'logged_at' => $now->format('Y-m-d H:i:s')
            ]);
        } finally {
            unlink($tempFile);
            Carbon::setTestNow();
        }
    }

    public function test_handle_imports_multiple_exercises()
    {
        $user = $this->createTestUser();
        $benchPress = $this->createGlobalExercise('Bench Press', 'bench_press');
        $squat = $this->createGlobalExercise('Squat', 'squat');
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ],
            [
            'exercise' => 'Squat',
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com'
            ])->assertExitCode(Command::SUCCESS);
            
            // Verify both exercises were imported
            $this->assertDatabaseHas('lift_logs', [
                'exercise_id' => $benchPress->id,
                'user_id' => $user->id
            ]);
            
            $this->assertDatabaseHas('lift_logs', [
                'exercise_id' => $squat->id,
                'user_id' => $user->id
            ]);
            
            // Verify lift sets
            $benchLiftLog = LiftLog::where('exercise_id', $benchPress->id)->first();
            $this->assertDatabaseHas('lift_sets', [
                'lift_log_id' => $benchLiftLog->id,
                'weight' => 225,
                'reps' => 5
            ]);
            
            $squatLiftLog = LiftLog::where('exercise_id', $squat->id)->first();
            $this->assertDatabaseHas('lift_sets', [
                'lift_log_id' => $squatLiftLog->id,
                'weight' => 315,
                'reps' => 8
            ]);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_import_exercise_defaults_sets_to_one_when_not_provided()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 225,
                    'reps' => 5,
                    // Note: 'sets' is not provided, should default to 1
                    'notes' => null,
            'exercise_type' => 'regular'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify only 1 lift set was created (default)
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $liftSets = LiftSet::where('lift_log_id', $liftLog->id)->get();
        
        $this->assertCount(1, $liftSets);
    }

    public function test_import_exercise_handles_zero_weight()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Plank', 'plank');
        
        $exerciseData = [
            'exercise' => 'Plank',
            'canonical_name' => 'plank',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 0,
                    'reps' => 60,
                    'sets' => 1,
                    'notes' => null,
            'exercise_type' => 'bodyweight'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift set was created with zero weight
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 60
        ]);
    }

    public function test_import_exercise_handles_decimal_weight()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Tricep Pushdown', 'tricep_pushdown');
        
        $exerciseData = [
            'exercise' => 'Tricep Pushdown',
            'canonical_name' => 'tricep_pushdown',
            'description' => 'Test exercise',
            'lift_logs' => [
                [
                    'weight' => 52.5,
                    'reps' => 8,
                    'sets' => 1,
                    'notes' => null,
            'exercise_type' => 'regular'
        ]
            ]
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift set was created with decimal weight
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 52.5,
            'reps' => 8
        ]);
    }

    public function test_running_command_multiple_times_with_overwrite_flag_creates_new_lift_logs()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            // Run the command first time
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])->assertExitCode(Command::SUCCESS);
            
            // Verify first import
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
            // Run the command second time with overwrite flag
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--overwrite' => true
            ])->assertExitCode(Command::SUCCESS);
            
            // Should still have only 1 lift log (old deleted, new created)
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
            // Verify the lift log has no comments (no notes provided in JSON)
            $liftLog = LiftLog::first();
            $this->assertNull($liftLog->comments);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_running_command_multiple_times_does_not_create_duplicate_exercises()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'New Exercise',
                'canonical_name' => 'new_exercise',
                'weight' => 100,
                'reps' => 10,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            // First run - exercise doesn't exist, would normally prompt user
            // For this test, we'll create the exercise manually to simulate user choosing "create"
            $this->createGlobalExercise('New Exercise', 'new_exercise');
            
            // Run the command first time
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--overwrite' => true  // Use overwrite to avoid prompts
            ])->assertExitCode(Command::SUCCESS);
            
            // Verify exercise count
            $exerciseCount = Exercise::where('canonical_name', 'new_exercise')->count();
            $this->assertEquals(1, $exerciseCount);
            
            // Run the command second time with overwrite
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--overwrite' => true  // Use overwrite to avoid prompts
            ])->assertExitCode(Command::SUCCESS);
            
            // Verify no duplicate exercises were created
            $exerciseCount = Exercise::where('canonical_name', 'new_exercise')->count();
            $this->assertEquals(1, $exerciseCount);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_duplicate_detection_finds_existing_lift_logs()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            // Should detect duplicate and prompt user
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])
            ->expectsChoice(
                'What would you like to do?',
                'Cancel import',
                ['Skip duplicates and import new ones only', 'Overwrite existing lift logs', 'Cancel import']
            )
            ->assertExitCode(Command::SUCCESS);
            
            // Should still have only the original lift log
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_overwrite_flag_deletes_existing_lift_logs()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--overwrite' => true
            ])->assertExitCode(Command::SUCCESS);
            
            // Should have new lift log (old one deleted, new one created)
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
            // Verify the new lift log has no comments (no notes provided in JSON)
            $newLiftLog = LiftLog::first();
            $this->assertNull($newLiftLog->comments);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_skip_duplicates_imports_only_new_exercises()
    {
        $user = $this->createTestUser();
        $benchPress = $this->createGlobalExercise('Bench Press', 'bench_press');
        $squat = $this->createGlobalExercise('Squat', 'squat');
        
        // Create existing lift log for bench press only
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $benchPress->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ],
            [
            'exercise' => 'Squat',
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])
            ->expectsChoice(
                'What would you like to do?',
                'Skip duplicates and import new ones only',
                ['Skip duplicates and import new ones only', 'Overwrite existing lift logs', 'Cancel import']
            )
            ->assertExitCode(Command::SUCCESS);
            
            // Should have 2 lift logs (1 existing + 1 new squat)
            $this->assertDatabaseCount('lift_logs', 2);
            $this->assertDatabaseCount('lift_sets', 2);
            
            // Verify squat was imported
            $squatLog = LiftLog::where('exercise_id', $squat->id)->first();
            $this->assertNotNull($squatLog);
            $this->assertNull($squatLog->comments);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_no_duplicates_imports_normally()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])->assertExitCode(Command::SUCCESS);
            
            // Should have 1 lift log
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_duplicate_detection_considers_different_weights_as_non_duplicates()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log with different weight
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 200, // Different weight
            'reps' => 5
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225, // Different weight
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])->assertExitCode(Command::SUCCESS);
            
            // Should have 2 lift logs (not considered duplicates)
            $this->assertDatabaseCount('lift_logs', 2);
            $this->assertDatabaseCount('lift_sets', 2);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_duplicate_detection_considers_different_reps_as_non_duplicates()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log with different reps
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 225,
            'reps' => 3 // Different reps
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5, // Different reps
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15'
            ])->assertExitCode(Command::SUCCESS);
            
            // Should have 2 lift logs (not considered duplicates)
            $this->assertDatabaseCount('lift_logs', 2);
            $this->assertDatabaseCount('lift_sets', 2);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_duplicate_detection_only_affects_same_date()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log on different date
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-14 10:00:00', // Different date
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        $exercises = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5,
                'sets' => 1,
            'exercise_type' => 'regular'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15' // Different date
            ])->assertExitCode(Command::SUCCESS);
            
            // Should have 2 lift logs (different dates, not duplicates)
            $this->assertDatabaseCount('lift_logs', 2);
            $this->assertDatabaseCount('lift_sets', 2);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_create_exercises_flag_automatically_creates_user_exercises()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Auto Created Exercise',
                'canonical_name' => 'auto_created_exercise',
                'exercise_type' => 'bodyweight',
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 1,
                    ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--create-exercises' => true
            ])->assertExitCode(Command::SUCCESS);
            
            // Verify user-specific exercise was created automatically
            $this->assertDatabaseHas('exercises', [
            'title' => 'Auto Created Exercise',
                'canonical_name' => 'auto_created_exercise',
                'user_id' => $user->id,
            'exercise_type' => 'bodyweight'
        ]);
            
            // Verify lift log was created
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_dry_run_flag_never_writes_to_database()
    {
        $user = $this->createTestUser();
        
        // Create exercises that will be found (no duplicates to avoid complexity)
        $exercise1 = $this->createGlobalExercise('Squat', 'squat');
        
        $exercises = [
            [
                'exercise' => 'Squat',
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8,
                'sets' => 3,
            'exercise_type' => 'regular'
        ],
            [
            'exercise' => 'New Exercise',
                'canonical_name' => 'new_exercise',
                'weight' => 100,
                'reps' => 10,
                'sets' => 2,
            'exercise_type' => 'bodyweight'
        ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            // Record initial database state
            $initialExerciseCount = Exercise::count();
            $initialLiftLogCount = LiftLog::count();
            $initialLiftSetCount = LiftSet::count();
            
            // Run dry-run with all flags that would normally create/modify data
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--dry-run' => true,
                '--create-exercises' => true
            ])
            ->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('DRY RUN MODE - No changes will be made to the database')
            ->expectsOutputToContain('Dry run completed:')
            ->expectsOutputToContain('Exercises that would be imported: 2')
            ->expectsOutputToContain('Total lift logs that would be imported: 2');
            
            // Verify NO changes were made to the database
            $this->assertEquals($initialExerciseCount, Exercise::count(), 'Dry run should not create any exercises');
            $this->assertEquals($initialLiftLogCount, LiftLog::count(), 'Dry run should not create any lift logs');
            $this->assertEquals($initialLiftSetCount, LiftSet::count(), 'Dry run should not create any lift sets');
            
            // Verify no new exercises were created (even with --create-exercises flag)
            $this->assertDatabaseMissing('exercises', [
                'canonical_name' => 'new_exercise'
            ]);
            
        } finally {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_sets_user_global_exercise_preference_during_import()
    {
        $user = $this->createTestUser();
        $user->update(['show_global_exercises' => true]); // Start with preference enabled
        
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 1,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, json_encode($jsonData));
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => $user->email,
                '--show-global-exercises' => 'false',
                '--no-interaction' => true
            ])
            ->expectsOutputToContain('Updated user\'s global exercise preference to: disabled')
            ->expectsOutputToContain('Previous preference was: enabled')
            ->expectsOutputToContain('✓ Imported lift logs for: Bench Press')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify the preference was actually updated
            $user->refresh();
            $this->assertFalse($user->show_global_exercises);
            
            // Verify the lift log was still imported
            $this->assertDatabaseHas('lift_logs', [
                'user_id' => $user->id,
                'exercise_id' => $exercise->id
            ]);
            
        } finally {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_previews_global_exercise_preference_change_in_dry_run()
    {
        $user = $this->createTestUser();
        $user->update(['show_global_exercises' => false]); // Start with preference disabled
        
        $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 1,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, json_encode($jsonData));
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => $user->email,
                '--show-global-exercises' => 'true',
                '--dry-run' => true,
                '--no-interaction' => true
            ])
            ->expectsOutputToContain('DRY RUN: Would set user\'s global exercise preference to: enabled')
            ->expectsOutputToContain('Current preference: disabled')
            ->expectsOutputToContain('DRY RUN: Would import lift logs for: Bench Press')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify the preference was NOT actually updated in dry run
            $user->refresh();
            $this->assertFalse($user->show_global_exercises);
            
            // Verify no lift log was created in dry run
            $this->assertDatabaseMissing('lift_logs', [
                'user_id' => $user->id
            ]);
            
        } finally {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_validates_global_exercise_preference_option()
    {
        $user = $this->createTestUser();
        
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 1,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, json_encode($jsonData));
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => $user->email,
                '--show-global-exercises' => 'invalid-value',
                '--no-interaction' => true
            ])
            ->expectsOutputToContain('Invalid value for --show-global-exercises. Use \'true\' or \'false\'.')
            ->assertExitCode(Command::FAILURE);
            
        } finally {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_works_without_global_exercise_preference_option()
    {
        $user = $this->createTestUser();
        // Set a specific preference value to test against
        $user->update(['show_global_exercises' => false]);
        $originalPreference = $user->show_global_exercises;
        
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 1,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, json_encode($jsonData));
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => $user->email,
                '--no-interaction' => true
            ])
            ->expectsOutputToContain('✓ Imported lift logs for: Bench Press')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify the preference was NOT changed when option not provided
            $user->refresh();
            $this->assertEquals($originalPreference, $user->show_global_exercises);
            
            // Verify the lift log was still imported
            $this->assertDatabaseHas('lift_logs', [
                'user_id' => $user->id,
                'exercise_id' => $exercise->id
            ]);
            
        } finally {
            unlink($tempFile);
        }
    }

    /** @test */
    public function it_shows_current_preference_in_dry_run_without_option()
    {
        $user = $this->createTestUser();
        $user->update(['show_global_exercises' => false]);
        
        $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $jsonData = [
            [
                'exercise' => 'Bench Press',
                'canonical_name' => 'bench_press',
                'description' => 'Barbell bench press',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5,
                        'sets' => 1,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_import');
        file_put_contents($tempFile, json_encode($jsonData));
        
        try {
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => $user->email,
                '--dry-run' => true,
                '--no-interaction' => true
            ])
            ->expectsOutputToContain('Current user\'s global exercise preference: disabled')
            ->expectsOutputToContain('DRY RUN: Would import lift logs for: Bench Press')
            ->assertExitCode(Command::SUCCESS);
            
            // Verify the preference was NOT changed in dry run
            $user->refresh();
            $this->assertFalse($user->show_global_exercises);
            
        } finally {
            unlink($tempFile);
        }
    }
}