<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_shows_deleted_users_when_no_argument_provided()
    {
        // Create active and deleted users
        $activeUser = User::factory()->create(['name' => 'Active User']);
        $deletedUser1 = User::factory()->create(['name' => 'Deleted User 1']);
        $deletedUser2 = User::factory()->create(['name' => 'Deleted User 2']);
        
        $deletedUser1->delete();
        $deletedUser2->delete();
        
        $this->artisan('user:restore')
            ->expectsOutput('Deleted users:')
            ->expectsOutput('To restore a user, run: php artisan user:restore <user_id|email|name>')
            ->assertExitCode(0);
    }

    public function test_command_shows_no_deleted_users_message_when_none_exist()
    {
        // Create only active users
        User::factory()->create(['name' => 'Active User']);
        
        $this->artisan('user:restore')
            ->expectsOutput('No deleted users found.')
            ->assertExitCode(0);
    }

    public function test_command_restores_user_by_id()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        $user->delete(); // This cascades to associated data
        
        $this->artisan('user:restore', ['user' => $user->id])
            ->expectsOutput('Found deleted user:')
            ->expectsOutput('Related data that will be reconnected:')
            ->expectsOutput('• 1 exercise(s) (1 will be restored)')
            ->expectsOutput('• 1 lift log(s) (1 will be restored)')
            ->expectsConfirmation('Do you want to restore this user?', 'yes')
            ->assertExitCode(0);
        
        // Verify user and associated data are restored
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_logs', ['id' => $liftLog->id, 'deleted_at' => null]);
    }

    public function test_command_restores_user_by_email()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        $user->delete();
        
        $this->artisan('user:restore', ['user' => 'test@example.com'])
            ->expectsOutput('Found deleted user:')
            ->expectsConfirmation('Do you want to restore this user?', 'yes')
            ->assertExitCode(0);
        
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_command_restores_user_by_name()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        $user->delete();
        
        $this->artisan('user:restore', ['user' => 'Test User'])
            ->expectsOutput('Found deleted user:')
            ->expectsConfirmation('Do you want to restore this user?', 'yes')
            ->assertExitCode(0);
        
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_command_handles_case_insensitive_name_search()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        $user->delete();
        
        $this->artisan('user:restore', ['user' => 'test user'])
            ->expectsOutput('Found deleted user:')
            ->expectsConfirmation('Do you want to restore this user?', 'yes')
            ->assertExitCode(0);
    }

    public function test_command_shows_error_when_user_not_found()
    {
        $this->artisan('user:restore', ['user' => 'nonexistent@example.com'])
            ->expectsOutput('No deleted user found matching: nonexistent@example.com')
            ->assertExitCode(1);
    }

    public function test_command_shows_error_when_user_is_not_deleted()
    {
        $user = User::factory()->create(['email' => 'active@example.com']);
        
        $this->artisan('user:restore', ['user' => 'active@example.com'])
            ->expectsOutput('No deleted user found matching: active@example.com')
            ->assertExitCode(1);
    }

    public function test_command_cancels_restoration_when_user_declines()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        $user->delete();
        
        $this->artisan('user:restore', ['user' => $user->id])
            ->expectsOutput('Found deleted user:')
            ->expectsConfirmation('Do you want to restore this user?', 'no')
            ->expectsOutput('Restoration cancelled.')
            ->assertExitCode(0);
        
        // Verify user is still deleted
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_command_shows_related_data_counts()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        // Create various associated data
        $exercise1 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id]);
        $alias1 = ExerciseAlias::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise1->id]);
        $liftLog1 = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise1->id]);
        $liftLog2 = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise2->id]);
        $liftLog3 = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise2->id]);
        
        $user->delete(); // This cascades to associated data
        
        $this->artisan('user:restore', ['user' => $user->id])
            ->expectsOutput('• 2 exercise(s) (2 will be restored)')
            ->expectsOutput('• 3 lift log(s) (3 will be restored)')
            ->expectsOutput('• 1 exercise alias(es) (1 will be restored)')
            ->expectsOutput('Note: Soft-deleted associated data will be automatically restored.')
            ->expectsConfirmation('Do you want to restore this user?', 'no')
            ->assertExitCode(0);
    }

    public function test_command_shows_mixed_data_counts_when_some_data_not_deleted()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        
        // Create associated data
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create(['user_id' => $user->id, 'exercise_id' => $exercise->id]);
        
        // Soft delete user (cascades to associated data)
        $user->delete();
        
        // Manually restore the exercise (simulating mixed state)
        $exercise->restore();
        
        $this->artisan('user:restore', ['user' => $user->id])
            ->expectsOutput('• 1 exercise(s)')
            ->expectsOutput('• 1 lift log(s) (1 will be restored)')
            ->expectsConfirmation('Do you want to restore this user?', 'no')
            ->assertExitCode(0);
    }

    public function test_command_handles_user_with_no_associated_data()
    {
        $user = User::factory()->create(['name' => 'Lonely User']);
        
        $user->delete();
        
        $this->artisan('user:restore', ['user' => $user->id])
            ->expectsOutput('Found deleted user:')
            ->doesntExpectOutput('Related data that will be reconnected:')
            ->expectsConfirmation('Do you want to restore this user?', 'yes')
            ->assertExitCode(0);
    }
}