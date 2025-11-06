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

class ImportJsonLiftLogUserExerciseDuplicateTest extends TestCase
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

    private function createTestJsonFile(array $exercises): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
        file_put_contents($tempFile, json_encode($exercises));
        return $tempFile;
    }

    public function test_find_duplicates_detects_user_specific_exercises()
    {
        $user = $this->createTestUser();
        
        // Create a user-specific exercise (not global)
        $userExercise = Exercise::create([
            'title' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'description' => 'User-specific exercise',
            'user_id' => $user->id, // User-specific exercise
            'exercise_type' => 'regular'
        ]);
        
        // Create existing lift log for this user exercise
        $existingLiftLog = LiftLog::create([
            'exercise_id' => $userExercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Existing log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $existingLiftLog->id,
            'weight' => 100,
            'reps' => 10
        ]);
        
        $exercises = [
            [
                'exercise' => 'Custom Exercise',
                'canonical_name' => 'custom_exercise',
                'description' => 'User-specific exercise',
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
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
        $this->assertEquals('custom_exercise', $duplicates[0]['canonical_name']);
        $this->assertEquals(100, $duplicates[0]['weight']);
        $this->assertEquals(10, $duplicates[0]['reps']);
        $this->assertEquals($userExercise->id, $duplicates[0]['exercise_id']);
    }

    public function test_running_command_multiple_times_with_create_exercises_flag_prevents_duplicates()
    {
        $user = $this->createTestUser();
        
        $exercises = [
            [
                'exercise' => 'New Custom Exercise',
                'canonical_name' => 'new_custom_exercise',
                'description' => 'A new user-specific exercise',
                'lift_logs' => [
                    [
                        'weight' => 150,
                        'reps' => 8,
                        'sets' => 1,
                        'notes' => null,
            'exercise_type' => 'regular'
        ]
                ]
            ]
        ];
        
        $tempFile = $this->createTestJsonFile($exercises);
        
        try {
            // First run - should create the exercise and lift log
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--create-exercises' => true,
                '--overwrite' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Verify first import
            $this->assertDatabaseCount('exercises', 1);
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
            $exercise = Exercise::first();
            $this->assertEquals('new_custom_exercise', $exercise->canonical_name);
            $this->assertEquals($user->id, $exercise->user_id);
            
            // Second run - should detect duplicates and overwrite
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--create-exercises' => true,
                '--overwrite' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Should still have only 1 exercise, 1 lift log, and 1 lift set (no duplicates)
            $this->assertDatabaseCount('exercises', 1);
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
            // Third run - should still prevent duplicates
            $this->artisan('lift-log:import-json', [
                'file' => $tempFile,
                '--user-email' => 'test@example.com',
                '--date' => '2024-01-15',
                '--create-exercises' => true,
                '--overwrite' => true
            ])
            ->assertExitCode(Command::SUCCESS);
            
            // Should still have only 1 exercise, 1 lift log, and 1 lift set (no duplicates)
            $this->assertDatabaseCount('exercises', 1);
            $this->assertDatabaseCount('lift_logs', 1);
            $this->assertDatabaseCount('lift_sets', 1);
            
        } finally {
            unlink($tempFile);
        }
    }

    public function test_find_duplicates_checks_both_global_and_user_exercises()
    {
        $user = $this->createTestUser();
        
        // Create a global exercise
        $globalExercise = Exercise::create([
            'title' => 'Global Exercise',
            'canonical_name' => 'global_exercise',
            'description' => 'Global exercise',
            'user_id' => null, // Global exercise
            'exercise_type' => 'regular'
        ]);
        
        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'title' => 'User Exercise',
            'canonical_name' => 'user_exercise',
            'description' => 'User-specific exercise',
            'user_id' => $user->id, // User-specific exercise
            'exercise_type' => 'regular'
        ]);
        
        // Create existing lift logs for both exercises
        $globalLiftLog = LiftLog::create([
            'exercise_id' => $globalExercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'Global log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $globalLiftLog->id,
            'weight' => 200,
            'reps' => 5
        ]);
        
        $userLiftLog = LiftLog::create([
            'exercise_id' => $userExercise->id,
            'user_id' => $user->id,
            'logged_at' => '2024-01-15 11:00:00',
            'comments' => 'User log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $userLiftLog->id,
            'weight' => 100,
            'reps' => 10
        ]);
        
        $exercises = [
            [
                'exercise' => 'Global Exercise',
                'canonical_name' => 'global_exercise',
                'lift_logs' => [
                    [
                        'weight' => 200,
                        'reps' => 5,
                        'sets' => 1
                    ]
                ]
            ],
            [
                'exercise' => 'User Exercise',
                'canonical_name' => 'user_exercise',
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 1
                    ]
                ]
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user, $loggedAt]);
        
        // Should find duplicates for both global and user exercises
        $this->assertCount(2, $duplicates);
        
        $canonicalNames = array_column($duplicates, 'canonical_name');
        $this->assertContains('global_exercise', $canonicalNames);
        $this->assertContains('user_exercise', $canonicalNames);
    }

    public function test_user_exercises_from_different_users_are_not_considered_duplicates()
    {
        $user1 = $this->createTestUser();
        $user2 = User::factory()->create(['email' => 'user2@example.com']);
        
        // Create user-specific exercises for different users with same canonical name
        $user1Exercise = Exercise::create([
            'title' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'description' => 'User 1 exercise',
            'user_id' => $user1->id,
            'exercise_type' => 'regular'
        ]);
        
        $user2Exercise = Exercise::create([
            'title' => 'Custom Exercise',
            'canonical_name' => 'custom_exercise',
            'description' => 'User 2 exercise',
            'user_id' => $user2->id,
            'exercise_type' => 'regular'
        ]);
        
        // Create lift log for user2's exercise
        $user2LiftLog = LiftLog::create([
            'exercise_id' => $user2Exercise->id,
            'user_id' => $user2->id,
            'logged_at' => '2024-01-15 10:00:00',
            'comments' => 'User 2 log'
        ]);
        
        LiftSet::create([
            'lift_log_id' => $user2LiftLog->id,
            'weight' => 100,
            'reps' => 10
        ]);
        
        $exercises = [
            [
                'exercise' => 'Custom Exercise',
                'canonical_name' => 'custom_exercise',
                'lift_logs' => [
                    [
                        'weight' => 100,
                        'reps' => 10,
                        'sets' => 1
                    ]
                ]
            ]
        ];
        
        $command = new ImportJsonLiftLog();
        $loggedAt = Carbon::parse('2024-01-15');
        
        // Check for duplicates for user1 - should not find any because user2's data shouldn't interfere
        $duplicates = $this->callPrivateMethod($command, 'findDuplicates', [$exercises, $user1, $loggedAt]);
        
        $this->assertCount(0, $duplicates);
    }
}