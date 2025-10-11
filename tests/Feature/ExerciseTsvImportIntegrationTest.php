<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTsvImportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $anotherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create regular users
        $this->user = User::factory()->create(['name' => 'Test User']);
        $this->anotherUser = User::factory()->create(['name' => 'Another User']);
        
        // Create admin user
        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);
    }

    public function test_admin_importing_global_exercises_that_conflict_with_existing_user_exercises()
    {
        // Create existing user exercises for both users
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Push Ups',
            'description' => 'User personal push ups',
            'is_bodyweight' => true,
        ]);

        Exercise::create([
            'user_id' => $this->anotherUser->id,
            'title' => 'Squats',
            'description' => 'Another user squats',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Deadlifts',
            'description' => 'User deadlifts',
            'is_bodyweight' => false,
        ]);

        // Admin imports global exercises with same names
        $tsvData = "Push Ups\tGlobal push ups exercise\ttrue\n" .
                   "Squats\tGlobal squats exercise\tfalse\n" .
                   "Deadlifts\tGlobal deadlifts exercise\tfalse\n" .
                   "New Global Exercise\tBrand new global exercise\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify all global exercises were created successfully
        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Push Ups',
            'description' => 'Global push ups exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Squats',
            'description' => 'Global squats exercise',
            'is_bodyweight' => false,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Deadlifts',
            'description' => 'Global deadlifts exercise',
            'is_bodyweight' => false,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'New Global Exercise',
            'description' => 'Brand new global exercise',
            'is_bodyweight' => true,
        ]);

        // Verify user exercises still exist unchanged
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Push Ups',
            'description' => 'User personal push ups',
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->anotherUser->id,
            'title' => 'Squats',
            'description' => 'Another user squats',
        ]);

        // Verify we now have both global and user versions
        $this->assertEquals(2, Exercise::whereRaw('LOWER(title) = ?', ['push ups'])->count());
        $this->assertEquals(2, Exercise::whereRaw('LOWER(title) = ?', ['squats'])->count());
        $this->assertEquals(2, Exercise::whereRaw('LOWER(title) = ?', ['deadlifts'])->count());
        $this->assertEquals(1, Exercise::whereRaw('LOWER(title) = ?', ['new global exercise'])->count());

        // Verify success message shows all imports
        $successMessage = session('success');
        $this->assertStringContainsString('Imported 4 new global exercises:', $successMessage);
        $this->assertStringContainsString('• Push Ups (bodyweight)', $successMessage);
        $this->assertStringContainsString('• Squats', $successMessage);
        $this->assertStringContainsString('• Deadlifts', $successMessage);
        $this->assertStringContainsString('• New Global Exercise (bodyweight)', $successMessage);
    }

    public function test_user_importing_exercises_that_conflict_with_existing_global_exercises()
    {
        // Create existing global exercises
        Exercise::create([
            'user_id' => null,
            'title' => 'Bench Press',
            'description' => 'Global bench press',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => null,
            'title' => 'Pull Ups',
            'description' => 'Global pull ups',
            'is_bodyweight' => true,
        ]);

        Exercise::create([
            'user_id' => null,
            'title' => 'Rows',
            'description' => 'Global rows',
            'is_bodyweight' => false,
        ]);

        // User tries to import exercises with same names plus some new ones
        $tsvData = "Bench Press\tUser bench press\tfalse\n" .
                   "Pull Ups\tUser pull ups\ttrue\n" .
                   "Rows\tUser rows\tfalse\n" .
                   "User Specific Exercise\tOnly for this user\ttrue\n" .
                   "Another User Exercise\tAnother personal exercise\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify global exercises remain unchanged
        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Bench Press',
            'description' => 'Global bench press',
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Pull Ups',
            'description' => 'Global pull ups',
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Rows',
            'description' => 'Global rows',
        ]);

        // Verify no user exercises were created with conflicting names
        $this->assertDatabaseMissing('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
        ]);

        $this->assertDatabaseMissing('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Pull Ups',
        ]);

        $this->assertDatabaseMissing('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Rows',
        ]);

        // Verify new user exercises were created
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'User Specific Exercise',
            'description' => 'Only for this user',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Another User Exercise',
            'description' => 'Another personal exercise',
            'is_bodyweight' => false,
        ]);

        // Verify success message shows imports and skips
        $successMessage = session('success');
        $this->assertStringContainsString('Imported 2 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• User Specific Exercise (bodyweight)', $successMessage);
        $this->assertStringContainsString('• Another User Exercise', $successMessage);
        $this->assertStringContainsString('Skipped 3 exercises:', $successMessage);
        $this->assertStringContainsString('• Bench Press - Exercise \'Bench Press\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• Pull Ups - Exercise \'Pull Ups\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• Rows - Exercise \'Rows\' conflicts with existing global exercise', $successMessage);
    }

    public function test_mixed_scenarios_with_both_global_and_user_exercises_in_database()
    {
        // Create a complex scenario with existing global and user exercises
        Exercise::create([
            'user_id' => null,
            'title' => 'Global Exercise A',
            'description' => 'Global A description',
            'is_bodyweight' => true,
        ]);

        Exercise::create([
            'user_id' => null,
            'title' => 'Shared Name Exercise',
            'description' => 'Global shared exercise',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Exercise A',
            'description' => 'User A same data',
            'is_bodyweight' => true,
        ]);

        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Exercise B',
            'description' => 'User B old description',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => $this->anotherUser->id,
            'title' => 'Another User Exercise',
            'description' => 'Another user exercise',
            'is_bodyweight' => true,
        ]);

        // User imports exercises with various conflict scenarios
        $tsvData = "Global Exercise A\tUser attempt at global\tfalse\n" .          // Should be skipped - conflicts with global
                   "Shared Name Exercise\tUser version\ttrue\n" .                   // Should be skipped - conflicts with global
                   "User Exercise A\tUser A same data\ttrue\n" .                    // Should be skipped - same data
                   "User Exercise B\tUser B updated description\ttrue\n" .          // Should be updated
                   "Another User Exercise\tDifferent user exercise\tfalse\n" .      // Should be imported - different user
                   "Brand New Exercise\tCompletely new\ttrue\n" .                   // Should be imported
                   "Case Sensitive Test\tTest description\tfalse\n" .               // Should be imported
                   "case sensitive test\tDifferent case\ttrue";                     // Should be updated - case insensitive match with first one

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify global exercises remain unchanged
        $globalA = Exercise::global()->where('title', 'Global Exercise A')->first();
        $this->assertEquals('Global A description', $globalA->description);
        $this->assertTrue($globalA->is_bodyweight);

        $globalShared = Exercise::global()->where('title', 'Shared Name Exercise')->first();
        $this->assertEquals('Global shared exercise', $globalShared->description);
        $this->assertFalse($globalShared->is_bodyweight);

        // Verify user exercise updates
        $userB = Exercise::userSpecific($this->user->id)->where('title', 'User Exercise B')->first();
        $this->assertEquals('User B updated description', $userB->description);
        $this->assertTrue($userB->is_bodyweight);

        // Verify new user exercises were created
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Another User Exercise',
            'description' => 'Different user exercise',
            'is_bodyweight' => false,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Brand New Exercise',
            'description' => 'Completely new',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Case Sensitive Test',
            'description' => 'Different case',  // Should be updated to the second value
            'is_bodyweight' => true,
        ]);

        // Verify case insensitive duplicate was not created
        $this->assertEquals(1, Exercise::userSpecific($this->user->id)
            ->whereRaw('LOWER(title) = ?', ['case sensitive test'])->count());

        // Verify another user's exercise remains unchanged
        $anotherUserExercise = Exercise::userSpecific($this->anotherUser->id)
            ->where('title', 'Another User Exercise')->first();
        $this->assertEquals('Another user exercise', $anotherUserExercise->description);
        $this->assertTrue($anotherUserExercise->is_bodyweight);

        // Verify detailed success message
        $successMessage = session('success');
        
        // Verify detailed success message
        $this->assertStringContainsString('Imported 3 new personal exercises:', $successMessage);
        $this->assertStringContainsString('Updated 2 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('Skipped 3 exercises:', $successMessage);
        $this->assertStringContainsString('• Global Exercise A - Exercise \'Global Exercise A\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• User Exercise A - Personal exercise \'User Exercise A\' already exists with same data', $successMessage);
    }

    public function test_detailed_import_result_lists_showing_specific_exercises_and_changes()
    {
        // Create existing exercises for update scenarios
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Update Test A',
            'description' => 'Original description A',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Update Test B',
            'description' => 'Original description B',
            'is_bodyweight' => true,
        ]);

        Exercise::create([
            'user_id' => null,
            'title' => 'Global Update Test',
            'description' => 'Original global description',
            'is_bodyweight' => false,
        ]);

        // Test detailed user import results
        $userTsvData = "New Exercise 1\tNew description 1\ttrue\n" .
                       "New Exercise 2\tNew description 2\tfalse\n" .
                       "Update Test A\tUpdated description A\ttrue\n" .
                       "Update Test B\tUpdated description B\tfalse\n" .
                       "Global Update Test\tShould be skipped\ttrue\n" .
                       "Same Data Test\tSame description\ttrue\n" .
                       "Same Data Test\tSame description\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $userTsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $successMessage = session('success');

        // Verify detailed imported exercises list
        $this->assertStringContainsString('Imported 3 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• New Exercise 1 (bodyweight)', $successMessage);
        $this->assertStringContainsString('• New Exercise 2', $successMessage);
        $this->assertStringContainsString('• Same Data Test (bodyweight)', $successMessage);

        // Verify detailed updated exercises list with change tracking
        $this->assertStringContainsString('Updated 2 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('• Update Test A (description: \'Original description A\' → \'Updated description A\', bodyweight: no → yes)', $successMessage);
        $this->assertStringContainsString('• Update Test B (description: \'Original description B\' → \'Updated description B\', bodyweight: yes → no)', $successMessage);

        // Verify detailed skipped exercises list with reasons
        $this->assertStringContainsString('Skipped 2 exercises:', $successMessage);
        $this->assertStringContainsString('• Global Update Test - Exercise \'Global Update Test\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• Same Data Test - Personal exercise \'Same Data Test\' already exists with same data', $successMessage);

        // Test detailed admin global import results
        $adminTsvData = "Global New 1\tGlobal description 1\ttrue\n" .
                        "Global New 2\tGlobal description 2\tfalse\n" .
                        "Global Update Test\tUpdated global description\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $adminTsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $globalSuccessMessage = session('success');

        // Verify detailed global import results
        $this->assertStringContainsString('Imported 2 new global exercises:', $globalSuccessMessage);
        $this->assertStringContainsString('• Global New 1 (bodyweight)', $globalSuccessMessage);
        $this->assertStringContainsString('• Global New 2', $globalSuccessMessage);

        $this->assertStringContainsString('Updated 1 existing global exercises:', $globalSuccessMessage);
        $this->assertStringContainsString('• Global Update Test (description: \'Original global description\' → \'Updated global description\', bodyweight: no → yes)', $globalSuccessMessage);
    }

    public function test_backward_compatibility_with_existing_exercise_data()
    {
        // Create exercises using the old format (before admin-managed exercises)
        $oldUserExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Old User Exercise',
            'description' => 'Created before admin system',
            'is_bodyweight' => true,
        ]);

        $oldAnotherUserExercise = Exercise::create([
            'user_id' => $this->anotherUser->id,
            'title' => 'Old Another User Exercise',
            'description' => 'Another old exercise',
            'is_bodyweight' => false,
        ]);

        // Verify old exercises work with new scopes
        $userExercises = Exercise::availableToUser($this->user->id)->get();
        $this->assertCount(1, $userExercises);
        $this->assertEquals('Old User Exercise', $userExercises->first()->title);

        $anotherUserExercises = Exercise::availableToUser($this->anotherUser->id)->get();
        $this->assertCount(1, $anotherUserExercises);
        $this->assertEquals('Old Another User Exercise', $anotherUserExercises->first()->title);

        // Test that old import functionality still works (personal exercises)
        $tsvData = "New Personal Exercise\tNew description\ttrue\n" .
                   "Old User Exercise\tUpdated old exercise\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify new exercise was created
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'New Personal Exercise',
            'description' => 'New description',
            'is_bodyweight' => true,
        ]);

        // Verify old exercise was updated
        $oldUserExercise->refresh();
        $this->assertEquals('Updated old exercise', $oldUserExercise->description);
        $this->assertFalse($oldUserExercise->is_bodyweight);

        // Verify another user's exercise remains unchanged
        $oldAnotherUserExercise->refresh();
        $this->assertEquals('Another old exercise', $oldAnotherUserExercise->description);
        $this->assertFalse($oldAnotherUserExercise->is_bodyweight);

        // Test that exercises are properly scoped after import
        $userAvailableExercises = Exercise::availableToUser($this->user->id)->get();
        $this->assertCount(2, $userAvailableExercises);
        
        $anotherUserAvailableExercises = Exercise::availableToUser($this->anotherUser->id)->get();
        $this->assertCount(1, $anotherUserAvailableExercises);

        // Add global exercises and verify they appear for all users
        $globalTsvData = "Global Exercise 1\tGlobal description 1\ttrue\n" .
                         "Global Exercise 2\tGlobal description 2\tfalse";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $globalTsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify global exercises are available to all users
        $userAvailableAfterGlobal = Exercise::availableToUser($this->user->id)->get();
        $this->assertCount(4, $userAvailableAfterGlobal); // 2 personal + 2 global

        $anotherUserAvailableAfterGlobal = Exercise::availableToUser($this->anotherUser->id)->get();
        $this->assertCount(3, $anotherUserAvailableAfterGlobal); // 1 personal + 2 global

        // Verify global exercises appear first in availableToUser scope (due to ordering)
        $userExercisesOrdered = Exercise::availableToUser($this->user->id)->get();
        $this->assertEquals('Old User Exercise', $userExercisesOrdered->first()->title); // User exercises first
        $this->assertEquals($this->user->id, $userExercisesOrdered->first()->user_id);
    }

    public function test_complex_integration_scenario_with_lift_logs()
    {
        // Create exercises with existing lift logs to test data integrity
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Global Bench Press',
            'description' => 'Global bench press',
            'is_bodyweight' => false,
        ]);

        $userExercise = Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'User Squats',
            'description' => 'User squats',
            'is_bodyweight' => false,
        ]);

        // Create lift logs for these exercises
        $globalLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $globalExercise->id,
            'logged_at' => now(),
            'notes' => 'Using global exercise',
        ]);

        $userLiftLog = LiftLog::create([
            'user_id' => $this->user->id,
            'exercise_id' => $userExercise->id,
            'logged_at' => now(),
            'notes' => 'Using user exercise',
        ]);

        // Create lift sets for the lift logs
        LiftSet::create([
            'lift_log_id' => $globalLiftLog->id,
            'weight' => 100,
            'reps' => 10,
            'set_number' => 1,
        ]);

        LiftSet::create([
            'lift_log_id' => $userLiftLog->id,
            'weight' => 150,
            'reps' => 8,
            'set_number' => 1,
        ]);

        // Verify exercises cannot be deleted due to lift logs
        $this->assertFalse($globalExercise->canBeDeletedBy($this->admin));
        $this->assertFalse($userExercise->canBeDeletedBy($this->user));

        // Test importing exercises that would conflict with exercises that have lift logs
        $tsvData = "Global Bench Press\tUser version of bench press\tfalse\n" .
                   "User Squats\tUpdated user squats\ttrue\n" .
                   "New Exercise\tBrand new exercise\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify global exercise with lift logs remains unchanged
        $globalExercise->refresh();
        $this->assertEquals('Global bench press', $globalExercise->description);
        $this->assertFalse($globalExercise->is_bodyweight);

        // Verify user exercise with lift logs was updated (allowed)
        $userExercise->refresh();
        $this->assertEquals('Updated user squats', $userExercise->description);
        $this->assertTrue($userExercise->is_bodyweight);

        // Verify new exercise was created
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'New Exercise',
            'description' => 'Brand new exercise',
            'is_bodyweight' => true,
        ]);

        // Verify lift logs still exist and point to correct exercises
        $this->assertTrue($globalLiftLog->fresh()->exercise->isGlobal());
        $this->assertEquals($this->user->id, $userLiftLog->fresh()->exercise->user_id);

        // Verify lift sets are still intact
        $this->assertEquals(100, $globalLiftLog->fresh()->liftSets->first()->weight);
        $this->assertEquals(150, $userLiftLog->fresh()->liftSets->first()->weight);

        // Test that availableToUser scope works correctly with exercises that have lift logs
        $availableExercises = Exercise::availableToUser($this->user->id)->get();
        $this->assertCount(3, $availableExercises); // Global + User + New

        // Verify the user can see both global and personal exercises
        $exerciseTitles = $availableExercises->pluck('title')->toArray();
        $this->assertContains('Global Bench Press', $exerciseTitles);
        $this->assertContains('User Squats', $exerciseTitles);
        $this->assertContains('New Exercise', $exerciseTitles);
    }

    public function test_import_performance_with_large_dataset()
    {
        // Create a large number of existing exercises to test performance
        $existingGlobalExercises = [];
        $existingUserExercises = [];

        // Create 50 global exercises
        for ($i = 1; $i <= 50; $i++) {
            $existingGlobalExercises[] = Exercise::create([
                'user_id' => null,
                'title' => "Global Exercise {$i}",
                'description' => "Global description {$i}",
                'is_bodyweight' => $i % 2 === 0,
            ]);
        }

        // Create 50 user exercises
        for ($i = 1; $i <= 50; $i++) {
            $existingUserExercises[] = Exercise::create([
                'user_id' => $this->user->id,
                'title' => "User Exercise {$i}",
                'description' => "User description {$i}",
                'is_bodyweight' => $i % 3 === 0,
            ]);
        }

        // Create a large TSV import with various scenarios
        $tsvLines = [];
        
        // Add new exercises
        for ($i = 1; $i <= 20; $i++) {
            $tsvLines[] = "New Exercise {$i}\tNew description {$i}\t" . ($i % 2 === 0 ? 'true' : 'false');
        }
        
        // Add updates to existing user exercises
        for ($i = 1; $i <= 10; $i++) {
            $tsvLines[] = "User Exercise {$i}\tUpdated description {$i}\t" . ($i % 2 === 0 ? 'false' : 'true');
        }
        
        // Add conflicts with global exercises
        for ($i = 1; $i <= 10; $i++) {
            $tsvLines[] = "Global Exercise {$i}\tUser attempt {$i}\t" . ($i % 2 === 0 ? 'true' : 'false');
        }

        $tsvData = implode("\n", $tsvLines);

        $startTime = microtime(true);

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify the import completed in reasonable time (less than 5 seconds)
        $this->assertLessThan(5.0, $executionTime, 'Import should complete in under 5 seconds');

        // Verify correct counts
        $successMessage = session('success');
        $this->assertStringContainsString('Imported 20 new personal exercises:', $successMessage);
        $this->assertStringContainsString('Updated 10 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('Skipped 10 exercises:', $successMessage);

        // Verify database state
        $totalUserExercises = Exercise::userSpecific($this->user->id)->count();
        $this->assertEquals(70, $totalUserExercises); // 50 original + 20 new (updates don't change count)

        $totalGlobalExercises = Exercise::global()->count();
        $this->assertEquals(50, $totalGlobalExercises); // Should remain unchanged

        // Verify availableToUser scope works efficiently with large dataset
        $startTime = microtime(true);
        $availableExercises = Exercise::availableToUser($this->user->id)->get();
        $endTime = microtime(true);
        $queryTime = $endTime - $startTime;

        $this->assertLessThan(1.0, $queryTime, 'availableToUser query should be fast');
        $this->assertCount(120, $availableExercises); // 70 user + 50 global
    }

    public function test_concurrent_import_scenarios()
    {
        // Simulate concurrent imports by different users
        $globalExercise = Exercise::create([
            'user_id' => null,
            'title' => 'Shared Global Exercise',
            'description' => 'Global exercise',
            'is_bodyweight' => false,
        ]);

        // User 1 imports exercises
        $user1TsvData = "User 1 Exercise\tUser 1 description\ttrue\n" .
                        "Shared Global Exercise\tUser 1 attempt\tfalse\n" .
                        "Common Name Exercise\tUser 1 version\ttrue";

        $response1 = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $user1TsvData
            ]);

        // User 2 imports exercises with some overlapping names
        $user2TsvData = "User 2 Exercise\tUser 2 description\tfalse\n" .
                        "Shared Global Exercise\tUser 2 attempt\ttrue\n" .
                        "Common Name Exercise\tUser 2 version\tfalse";

        $response2 = $this->actingAs($this->anotherUser)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $user2TsvData
            ]);

        // Both should succeed
        $response1->assertRedirect(route('exercises.index'));
        $response1->assertSessionHas('success');
        $response2->assertRedirect(route('exercises.index'));
        $response2->assertSessionHas('success');

        // Verify each user has their own exercises
        $user1Exercises = Exercise::userSpecific($this->user->id)->get();
        $this->assertCount(2, $user1Exercises); // User 1 Exercise + Common Name Exercise

        $user2Exercises = Exercise::userSpecific($this->anotherUser->id)->get();
        $this->assertCount(2, $user2Exercises); // User 2 Exercise + Common Name Exercise

        // Verify global exercise remains unchanged
        $globalExercise->refresh();
        $this->assertEquals('Global exercise', $globalExercise->description);

        // Verify both users can have exercises with the same name (different from global)
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Common Name Exercise',
            'description' => 'User 1 version',
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->anotherUser->id,
            'title' => 'Common Name Exercise',
            'description' => 'User 2 version',
        ]);

        // Verify availableToUser works correctly for each user
        $user1Available = Exercise::availableToUser($this->user->id)->get();
        $this->assertCount(3, $user1Available); // 2 personal + 1 global

        $user2Available = Exercise::availableToUser($this->anotherUser->id)->get();
        $this->assertCount(3, $user2Available); // 2 personal + 1 global
    }
}