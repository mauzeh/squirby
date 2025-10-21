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
            'is_bodyweight' => false,
            'user_id' => null // Global exercise
        ]);
    }

    private function createTestJsonFile(array $exercises): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, json_encode($exercises));
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
                'is_bodyweight' => false
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
            'weight' => 225,
            'reps' => 5,
            'sets' => 1,
            'is_bodyweight' => false
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift log was created
        $this->assertDatabaseHas('lift_logs', [
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'comments' => 'Imported from JSON file'
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
            'weight' => 225,
            'reps' => 5,
            'sets' => 3,
            'is_bodyweight' => false
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
            'weight' => 0,
            'reps' => 90,
            'sets' => 1,
            'is_bodyweight' => true,
            'notes' => 'time in seconds'
        ];
        
        $command = $this->getCommand();
        $loggedAt = Carbon::now();
        
        $this->callPrivateMethod($command, 'importExercise', [$exerciseData, $user, $loggedAt]);
        
        // Verify lift set was created with notes
        $liftLog = LiftLog::where('exercise_id', $exercise->id)->first();
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 90,
            'notes' => 'time in seconds'
        ]);
    }

    public function test_find_or_create_exercise_finds_existing_global_exercise()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        $exerciseData = [
            'exercise' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'is_bodyweight' => false
        ];
        
        $command = $this->getCommand();
        $result = $this->callPrivateMethod($command, 'findOrCreateExercise', [$exerciseData, $user]);
        
        $this->assertEquals($exercise->id, $result->id);
    }

    public function test_create_new_global_exercise()
    {
        $exerciseData = [
            'exercise' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'is_bodyweight' => true
        ];
        
        $command = $this->getCommand();
        $result = $this->callPrivateMethod($command, 'createNewGlobalExercise', [$exerciseData]);
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'New Exercise',
            'canonical_name' => 'new_exercise',
            'is_bodyweight' => true,
            'user_id' => null,
            'description' => 'Imported from JSON file'
        ]);
        
        $this->assertEquals('New Exercise', $result->title);
        $this->assertEquals('new_exercise', $result->canonical_name);
        $this->assertTrue($result->is_bodyweight);
        $this->assertNull($result->user_id);
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
                'is_bodyweight' => false
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
                'is_bodyweight' => false
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
                'is_bodyweight' => false
            ],
            [
                'exercise' => 'Squat',
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8,
                'sets' => 1,
                'is_bodyweight' => false
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
            'weight' => 225,
            'reps' => 5,
            'is_bodyweight' => false
            // Note: 'sets' is not provided
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
            'weight' => 0,
            'reps' => 60,
            'sets' => 1,
            'is_bodyweight' => true
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
            'weight' => 52.5,
            'reps' => 8,
            'sets' => 1,
            'is_bodyweight' => false
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
}