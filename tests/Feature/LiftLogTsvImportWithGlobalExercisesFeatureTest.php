<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LiftLogTsvImportWithGlobalExercisesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $adminRole = Role::create(['name' => 'Admin']);
        
        // Create users
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function user_can_import_lift_logs_using_global_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Bench Press',
            'description' => 'Global exercise for bench press',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tGlobal Bench Press\t135\t8\t3\tGood form";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);

        // Verify lift log was created using the global exercise
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals(3, $liftLog->liftSets->count());
        $this->assertEquals('Good form', $liftLog->comments);
    }

    /** @test */
    public function user_can_import_lift_logs_using_personal_exercises()
    {
        // Create a user-specific exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Squat',
            'description' => 'User-specific squat exercise',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tPersonal Squat\t185\t5\t5\tDeep squats";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);

        // Verify lift log was created using the user exercise
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals(5, $liftLog->liftSets->count());
        $this->assertEquals('Deep squats', $liftLog->comments);
    }

    /** @test */
    public function user_exercise_takes_priority_over_global_exercise_with_same_name()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Deadlift',
            'description' => 'Global deadlift exercise',
            'is_bodyweight' => false,
        ]);

        // Create a user exercise with the same name
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Deadlift',
            'description' => 'User-specific deadlift exercise',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tDeadlift\t225\t3\t3\tConventional style";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');

        // Verify lift log was created using the user exercise, not the global one
        $liftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals($userExercise->id, $liftLog->exercise_id);

        // Verify no lift log was created for the global exercise
        $globalLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNull($globalLiftLog);
    }

    /** @test */
    public function user_can_import_mixed_global_and_personal_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Pull-ups',
            'description' => 'Global pull-up exercise',
            'is_bodyweight' => true,
        ]);

        // Create a user exercise
        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Personal Dips',
            'description' => 'User-specific dips exercise',
            'is_bodyweight' => true,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tGlobal Pull-ups\t0\t10\t3\tBodyweight\n" .
                   "1/15/2024\t6:30 AM\tPersonal Dips\t0\t12\t3\tGood depth";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('2 lift log(s) imported', $successMessage);

        // Verify both lift logs were created
        $globalLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $userLiftLog = LiftLog::where('user_id', $this->user->id)
            ->where('exercise_id', $userExercise->id)
            ->first();

        $this->assertNotNull($globalLiftLog);
        $this->assertNotNull($userLiftLog);
        $this->assertEquals('Bodyweight', $globalLiftLog->comments);
        $this->assertEquals('Good depth', $userLiftLog->comments);
    }

    /** @test */
    public function user_cannot_import_lift_logs_for_other_users_exercises()
    {
        $otherUser = User::factory()->create();
        
        // Create an exercise for another user
        $otherUserExercise = Exercise::create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Exercise',
            'description' => 'Exercise belonging to another user',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tOther User Exercise\t135\t8\t3\tShould not work";

        $response = $this->actingAs($this->user)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('Other User Exercise', $errorMessage);

        // Verify no lift log was created
        $this->assertDatabaseCount('lift_logs', 0);
    }

    /** @test */
    public function admin_can_import_lift_logs_using_global_exercises()
    {
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Admin Global Exercise',
            'description' => 'Global exercise for admin testing',
            'is_bodyweight' => false,
        ]);

        $tsvData = "1/15/2024\t6:00 AM\tAdmin Global Exercise\t200\t5\t4\tAdmin workout";

        $response = $this->actingAs($this->admin)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData,
                'date' => '2024-01-15'
            ]);

        $response->assertRedirect(route('lift-logs.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('1 lift log(s) imported', $successMessage);

        // Verify lift log was created for the admin using the global exercise
        $liftLog = LiftLog::where('user_id', $this->admin->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();

        $this->assertNotNull($liftLog);
        $this->assertEquals(4, $liftLog->liftSets->count());
        $this->assertEquals('Admin workout', $liftLog->comments);
    }

    /** @test */
    public function lift_log_import_works_correctly_with_exercise_scoping_integration()
    {
        // Create multiple users to test scoping
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // Create a global exercise
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Squat',
            'description' => 'Global squat exercise',
            'is_bodyweight' => false,
        ]);

        // Create user-specific exercises
        $user1Exercise = Exercise::create([
            'user_id' => $user1->id,
            'title' => 'User1 Bench Press',
            'description' => 'User1 specific bench press',
            'is_bodyweight' => false,
        ]);

        $user2Exercise = Exercise::create([
            'user_id' => $user2->id,
            'title' => 'User2 Deadlift',
            'description' => 'User2 specific deadlift',
            'is_bodyweight' => false,
        ]);

        // Test that user1 can import using global exercise
        $tsvData1 = "1/15/2024\t6:00 AM\tGlobal Squat\t185\t5\t3\tUser1 using global";
        
        $response1 = $this->actingAs($user1)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData1,
                'date' => '2024-01-15'
            ]);

        $response1->assertRedirect(route('lift-logs.index'));
        $response1->assertSessionHas('success');

        // Test that user1 can import using their own exercise
        $tsvData2 = "1/15/2024\t6:30 AM\tUser1 Bench Press\t135\t8\t3\tUser1 personal exercise";
        
        $response2 = $this->actingAs($user1)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData2,
                'date' => '2024-01-15'
            ]);

        $response2->assertRedirect(route('lift-logs.index'));
        $response2->assertSessionHas('success');

        // Test that user1 cannot import using user2's exercise
        $tsvData3 = "1/15/2024\t7:00 AM\tUser2 Deadlift\t225\t3\t3\tShould not work";
        
        $response3 = $this->actingAs($user1)
            ->post(route('lift-logs.import-tsv'), [
                'tsv_data' => $tsvData3,
                'date' => '2024-01-15'
            ]);

        $response3->assertRedirect(route('lift-logs.index'));
        $response3->assertSessionHas('error');

        // Verify the correct lift logs were created
        $this->assertEquals(2, LiftLog::where('user_id', $user1->id)->count());
        $this->assertEquals(0, LiftLog::where('user_id', $user2->id)->count());

        // Verify user1 has access to both global and personal exercises
        $user1LiftLog1 = LiftLog::where('user_id', $user1->id)
            ->where('exercise_id', $globalExercise->id)
            ->first();
        
        $user1LiftLog2 = LiftLog::where('user_id', $user1->id)
            ->where('exercise_id', $user1Exercise->id)
            ->first();

        $this->assertNotNull($user1LiftLog1);
        $this->assertNotNull($user1LiftLog2);
        $this->assertEquals('User1 using global', $user1LiftLog1->comments);
        $this->assertEquals('User1 personal exercise', $user1LiftLog2->comments);

        // Verify no lift log was created for user2's exercise
        $invalidLiftLog = LiftLog::where('user_id', $user1->id)
            ->where('exercise_id', $user2Exercise->id)
            ->first();
        
        $this->assertNull($invalidLiftLog);
    }
}