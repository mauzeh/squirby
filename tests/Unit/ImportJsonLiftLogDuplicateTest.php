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

class ImportJsonLiftLogDuplicateTest extends TestCase
{
    use RefreshDatabase;

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
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);
    }

    private function createTestJsonFile(array $exercises): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, json_encode($exercises));
        return $tempFile;
    }

    public function test_find_duplicates_detects_exact_matches()
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
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        $this->assertCount(1, $duplicates);
        $this->assertEquals('bench_press', $duplicates[0]['canonical_name']);
        $this->assertEquals(225, $duplicates[0]['weight']);
        $this->assertEquals(5, $duplicates[0]['reps']);
    }

    public function test_find_duplicates_ignores_different_weights()
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
                'description' => 'Test exercise',
                'lift_logs' => [
                    [
                        'weight' => 225, // Different weight
                        'reps' => 5,
                        'sets' => 1,
                        'notes' => null,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }

    public function test_find_duplicates_ignores_different_reps()
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
                'description' => 'Test exercise',
                'lift_logs' => [
                    [
                        'weight' => 225,
                        'reps' => 5, // Different reps
                        'sets' => 1,
                        'notes' => null,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }

    public function test_find_duplicates_ignores_different_dates()
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
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15'); // Different date
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }

    public function test_find_duplicates_ignores_different_users()
    {
        $user1 = $this->createTestUser();
        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create existing lift log for different user
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user2->id, // Different user
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
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user1, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }

    public function test_find_duplicates_skips_nonexistent_exercises()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'Nonexistent Exercise',
                'canonical_name' => 'nonexistent_exercise',
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
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }

    public function test_is_duplicate_correctly_identifies_matches()
    {
        $exerciseData = [
            'canonical_name' => 'bench_press',
            'lift_logs' => [
                [
                    'weight' => 225,
                    'reps' => 5
                ]
            ]
        ];
        
        $duplicates = [
            [
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5
            ],
            [
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'isDuplicate', [$exerciseData, $duplicates]);
        
        $this->assertTrue($result);
    }

    public function test_is_duplicate_correctly_identifies_non_matches()
    {
        $exerciseData = [
            'canonical_name' => 'deadlift',
            'lift_logs' => [
                [
                    'weight' => 275,
                    'reps' => 3
                ]
            ]
        ];
        
        $duplicates = [
            [
                'canonical_name' => 'bench_press',
                'weight' => 225,
                'reps' => 5
            ],
            [
                'canonical_name' => 'squat',
                'weight' => 315,
                'reps' => 8
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $result = $this->callPrivateMethod($command, 'isDuplicate', [$exerciseData, $duplicates]);
        
        $this->assertFalse($result);
    }

    public function test_delete_duplicate_lift_logs_removes_correct_logs()
    {
        $user = $this->createTestUser();
        $exercise = $this->createGlobalExercise('Bench Press', 'bench_press');
        
        // Create multiple lift logs
        $liftLog1 = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Log 1'
        ]);
        
        $liftSet1 = LiftSet::create([
            'lift_log_id' => $liftLog1->id,
            'weight' => 225,
            'reps' => 5
        ]);
        
        $liftLog2 = LiftLog::create([
            'exercise_id' => $exercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 11:00:00',
            'comments' => 'Log 2'
        ]);
        
        $liftSet2 = LiftSet::create([
            'lift_log_id' => $liftLog2->id,
            'weight' => 200, // Different weight - should not be deleted
            'reps' => 5
        ]);
        
        $duplicates = [
            [
                'exercise_id' => $exercise->id,
                'weight' => 225,
                'reps' => 5
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $this->callPrivateMethod($command, 'deleteDuplicateLiftLogs', [$duplicates, $user, $loggedAt]);
        
        // Should have soft-deleted liftLog1 and liftSet1, but kept liftLog2 and liftSet2
        $this->assertSoftDeleted($liftLog1);
        $this->assertSoftDeleted($liftSet1);
        $this->assertDatabaseHas('lift_logs', ['id' => $liftLog2->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_sets', ['id' => $liftSet2->id, 'deleted_at' => null]);
    }

    public function test_overwrite_choice_deletes_and_imports_new_data()
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
                'Overwrite existing lift logs',
                ['Skip duplicates and import new ones only', 'Overwrite existing lift logs', 'Cancel import']
            )
            ->assertExitCode(Command::SUCCESS);
            
            // The old log should be soft deleted, and a new one created
            $this->assertCount(1, LiftLog::all()); // Only one non-deleted log
            $this->assertCount(2, LiftLog::withTrashed()->get()); // Two logs total (one deleted, one new)
            $this->assertCount(1, LiftSet::all()); // Only one non-deleted set
            $this->assertCount(2, LiftSet::withTrashed()->get()); // Two sets total (one deleted, one new)
            
            $newLiftLog = LiftLog::first();
            $this->assertNull($newLiftLog->comments);
            
        } finally {
            unlink($tempFile);
        }
    }
}