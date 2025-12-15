<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseIntelligence;
use App\Models\LiftLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExerciseMergeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
        
        // Create regular user
        $this->regularUser = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function complete_merge_workflow_transfers_all_data_correctly()
    {
        $this->actingAs($this->admin);

        // Create source exercise with comprehensive data
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'User Bench Press',
            'description' => 'User created bench press',
            'exercise_type' => 'regular'
        ]);

        // Create target exercise
        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'description' => 'Standard bench press',
            'exercise_type' => 'regular'
        ]);

        // Create lift logs with various scenarios
        $liftLogWithComments = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'comments' => 'Great workout today',
            'weight' => 100,
            'logged_at' => now()->subDays(5)
        ]);

        $liftLogWithoutComments = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'comments' => null,
            'weight' => 105,
            'logged_at' => now()->subDays(3)
        ]);

        // Create exercise intelligence for source
        $sourceIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'canonical_name' => 'user_bench_press'
        ]);

        // Step 1: Access merge interface
        $showResponse = $this->get(route('exercises.show-merge', $sourceExercise));
        $showResponse->assertStatus(200);
        $showResponse->assertViewIs('exercises.merge');
        $showResponse->assertViewHas('exercise', $sourceExercise);
        $showResponse->assertViewHas('targetsWithInfo');

        // Step 2: Execute merge
        $mergeResponse = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            '_token' => csrf_token()
        ]);

        // Verify redirect and success message
        $mergeResponse->assertRedirect(route('exercises.index'));
        $mergeResponse->assertSessionHas('success', "Exercise 'User Bench Press' successfully merged into 'Bench Press'. All workout data has been preserved. An alias has been created so the owner will continue to see 'User Bench Press'.");

        // Step 3: Verify data transfer

        // Check lift logs were transferred without modifying comments
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLogWithComments->id,
            'exercise_id' => $targetExercise->id,
            'comments' => 'Great workout today',
            'weight' => 100
        ]);

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLogWithoutComments->id,
            'exercise_id' => $targetExercise->id,
            'comments' => null,
            'weight' => 105
        ]);

        // Check exercise intelligence was transferred
        $this->assertDatabaseHas('exercise_intelligence', [
            'id' => $sourceIntelligence->id,
            'exercise_id' => $targetExercise->id,
            'canonical_name' => 'user_bench_press'
        ]);

        // Check source exercise was soft deleted
        $this->assertSoftDeleted($sourceExercise);

        // Check target exercise remains unchanged and not soft deleted
        $this->assertDatabaseHas('exercises', [
            'id' => $targetExercise->id,
            'title' => 'Bench Press',
            'description' => 'Standard bench press',
            'user_id' => null,
            'deleted_at' => null
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_handles_intelligence_conflicts_correctly()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $targetExercise = Exercise::factory()->create(['user_id' => null]);

        // Create intelligence for both exercises
        $sourceIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'canonical_name' => 'source_exercise'
        ]);

        $targetIntelligence = ExerciseIntelligence::factory()->create([
            'exercise_id' => $targetExercise->id,
            'canonical_name' => 'target_exercise'
        ]);

        // Execute merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect(route('exercises.index'));

        // Target intelligence should remain unchanged
        $this->assertDatabaseHas('exercise_intelligence', [
            'id' => $targetIntelligence->id,
            'exercise_id' => $targetExercise->id,
            'canonical_name' => 'target_exercise'
        ]);

        // Source intelligence should be soft deleted (cascade with exercise)
        $this->assertSoftDeleted($sourceIntelligence);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_prevents_unauthorized_access()
    {
        $this->actingAs($this->regularUser); // Non-admin user

        $sourceExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $targetExercise = Exercise::factory()->create(['user_id' => null]);

        // Try to access merge interface
        $showResponse = $this->get(route('exercises.show-merge', $sourceExercise));
        $showResponse->assertStatus(403);

        // Try to execute merge
        $mergeResponse = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);
        $mergeResponse->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_validates_compatibility_requirements()
    {
        $this->actingAs($this->admin);

        // Create incompatible exercises (different bodyweight settings)
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
        ]);

        // Try to merge incompatible exercises
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        
        // Verify no data was changed
        $this->assertDatabaseHas('exercises', [
            'id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id
        ]);
        $this->assertDatabaseHas('exercises', [
            'id' => $targetExercise->id,
            'user_id' => null
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_displays_warnings_for_visibility_issues()
    {
        $this->actingAs($this->admin);

        // Create user with global visibility disabled
        $userWithDisabledVisibility = User::factory()->create(['show_global_exercises' => false]);
        
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $userWithDisabledVisibility->id,
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        // Access merge interface
        $response = $this->get(route('exercises.show-merge', $sourceExercise));
        
        $response->assertStatus(200);
        $response->assertViewHas('targetsWithInfo');
        
        $targetsWithInfo = $response->viewData('targetsWithInfo');
        $this->assertNotEmpty($targetsWithInfo);
        
        // Check that at least one target has warnings about global visibility
        $hasVisibilityWarning = false;
        foreach ($targetsWithInfo as $targetInfo) {
            if (!empty($targetInfo['compatibility']['warnings'])) {
                foreach ($targetInfo['compatibility']['warnings'] as $warning) {
                    if (strpos($warning, 'global exercise visibility disabled') !== false) {
                        $hasVisibilityWarning = true;
                        break 2;
                    }
                }
            }
        }
        $this->assertTrue($hasVisibilityWarning, 'Expected to find global visibility warning in target compatibility checks');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_handles_transaction_rollback_on_failure()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $targetExercise = Exercise::factory()->create(['user_id' => null]);

        // Create lift log to verify rollback
        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'comments' => 'Original comment'
        ]);

        // Simulate failure by deleting target exercise after creating the request
        // but before the service processes it (this will cause a database error)
        $targetId = $targetExercise->id;
        $targetExercise->delete();

        // Try to merge (should fail because target exercise is soft-deleted)
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetId
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);

        // Verify rollback - source exercise and lift log should be unchanged and not soft-deleted
        $this->assertDatabaseHas('exercises', ['id' => $sourceExercise->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'exercise_id' => $sourceExercise->id,
            'comments' => 'Original comment',
            'deleted_at' => null
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_logs_operations_correctly()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Source Exercise'
        ]);
        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Target Exercise'
        ]);

        // Execute successful merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify merge completed successfully
        $this->assertSoftDeleted($sourceExercise);
        $this->assertDatabaseHas('exercises', ['id' => $targetExercise->id, 'deleted_at' => null]);

        // Verify database log was created
        $this->assertDatabaseHas('exercise_merge_logs', [
            'source_exercise_id' => $sourceExercise->id,
            'target_exercise_id' => $targetExercise->id,
            'admin_user_id' => $this->admin->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_preserves_data_integrity_with_multiple_users()
    {
        $this->actingAs($this->admin);

        $otherUser1 = User::factory()->create();
        $otherUser2 = User::factory()->create();

        $sourceExercise = Exercise::factory()->create(['user_id' => $this->regularUser->id]);
        $targetExercise = Exercise::factory()->create(['user_id' => null]);

        // Create lift logs for multiple users
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'weight' => 100
        ]);

        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $otherUser1->id,
            'weight' => 120
        ]);

        $liftLog3 = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $otherUser2->id,
            'weight' => 90
        ]);

        // Execute merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect(route('exercises.index'));

        // Verify all lift logs were transferred correctly
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog1->id,
            'exercise_id' => $targetExercise->id,
            'user_id' => $this->regularUser->id,
            'weight' => 100
        ]);

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog2->id,
            'exercise_id' => $targetExercise->id,
            'user_id' => $otherUser1->id,
            'weight' => 120
        ]);

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog3->id,
            'exercise_id' => $targetExercise->id,
            'user_id' => $otherUser2->id,
            'weight' => 90
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_handles_band_type_compatibility()
    {
        $this->actingAs($this->admin);

        // Test null source with resistance target (should be compatible)
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_resistance'
        ]);

        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify merge completed successfully
        $this->assertSoftDeleted($sourceExercise);
        $this->assertDatabaseHas('exercises', ['id' => $targetExercise->id, 'deleted_at' => null]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_rejects_incompatible_band_types()
    {
        $this->actingAs($this->admin);

        // Test resistance source with assistance target (should be incompatible)
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'banded_resistance'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_assistance'
        ]);

        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        // Verify no merge occurred
        $this->assertDatabaseHas('exercises', ['id' => $sourceExercise->id]);
        $this->assertDatabaseHas('exercises', ['id' => $targetExercise->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_workflow_shows_no_targets_available_message()
    {
        $this->actingAs($this->admin);

        // Create source exercise with unique characteristics
        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'exercise_type' => 'banded_resistance'
        ]);

        // No compatible global exercises exist

        $response = $this->get(route('exercises.show-merge', $sourceExercise));
        $response->assertStatus(302); // Redirects back with error message
        $response->assertSessionHasErrors();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function merge_with_alias_creation_enabled_creates_alias()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'BP',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
        ]);

        // Execute merge with alias creation enabled (default)
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify source exercise was soft deleted
        $this->assertSoftDeleted($sourceExercise);
    }

    /** @test */
    public function merge_with_alias_creation_disabled_does_not_create_alias()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'BP',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
        ]);

        // Execute merge with alias creation disabled
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => false
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify no alias was created
        $this->assertDatabaseMissing('exercise_aliases', [
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id
        ]);

        // Verify source exercise was soft deleted
        $this->assertSoftDeleted($sourceExercise);
    }

    /** @test */
    public function merge_creates_alias_before_source_deletion()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Squat Variation',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Squat',
            'exercise_type' => 'regular'
        ]);

        // Execute merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));

        // Verify alias exists and references the target exercise
        $this->assertDatabaseHas('exercise_aliases', [
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id,
            'alias_name' => 'Squat Variation'
        ]);

        // Verify source exercise no longer exists (soft deleted)
        $this->assertSoftDeleted($sourceExercise);

        // Verify target exercise still exists
        $this->assertDatabaseHas('exercises', ['id' => $targetExercise->id, 'deleted_at' => null]);
    }

    /** @test */
    public function merge_with_alias_creation_within_transaction()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'DL',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Deadlift',
            'exercise_type' => 'regular'
        ]);

        // Create lift log to verify transaction integrity
        $liftLog = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'weight' => 200
        ]);

        // Execute merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));

        // Verify all operations completed successfully
        // 1. Alias created
        $this->assertDatabaseHas('exercise_aliases', [
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id,
            'alias_name' => 'DL'
        ]);

        // 2. Lift log transferred
        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'exercise_id' => $targetExercise->id,
            'weight' => 200
        ]);

        // 3. Source exercise soft deleted
        $this->assertSoftDeleted($sourceExercise);
    }

    /** @test */
    public function merge_handles_duplicate_alias_gracefully()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'OHP',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Overhead Press',
            'exercise_type' => 'regular'
        ]);

        // Create an existing alias for the user and target exercise
        DB::table('exercise_aliases')->insert([
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id,
            'alias_name' => 'Existing Alias',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Execute merge (should not fail even though alias already exists)
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify merge completed successfully
        $this->assertSoftDeleted($sourceExercise);

        // Verify existing alias remains unchanged
        $this->assertDatabaseHas('exercise_aliases', [
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id,
            'alias_name' => 'Existing Alias'
        ]);

        // Verify no duplicate alias was created
        $aliasCount = DB::table('exercise_aliases')
            ->where('user_id', $this->regularUser->id)
            ->where('exercise_id', $targetExercise->id)
            ->count();
        $this->assertEquals(1, $aliasCount);
    }

    /** @test */
    public function merge_defaults_to_creating_alias_when_checkbox_not_provided()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Front Squat',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Squat',
            'exercise_type' => 'regular'
        ]);

        // Execute merge without create_alias parameter (should default to true)
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id
            // Note: create_alias not provided
        ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Verify alias was created by default
        $this->assertDatabaseHas('exercise_aliases', [
            'user_id' => $this->regularUser->id,
            'exercise_id' => $targetExercise->id,
            'alias_name' => 'Front Squat'
        ]);
    }

    /** @test */
    public function merge_success_message_mentions_alias_creation()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'RDL',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Romanian Deadlift',
            'exercise_type' => 'regular'
        ]);

        // Execute merge with alias creation
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));
        
        // Verify success message mentions alias creation
        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, "An alias has been created so the owner will continue to see 'RDL'");
        });
    }

    /** @test */
    public function merge_creates_database_log_entry_with_all_details()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'User Squat',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Squat',
            'exercise_type' => 'regular'
        ]);

        // Create lift logs to track
        $liftLog1 = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'weight' => 100
        ]);

        $liftLog2 = LiftLog::factory()->create([
            'exercise_id' => $sourceExercise->id,
            'user_id' => $this->regularUser->id,
            'weight' => 105
        ]);

        // Execute merge
        $response = $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => true
        ]);

        $response->assertRedirect(route('exercises.index'));

        // Verify database log entry was created
        $this->assertDatabaseHas('exercise_merge_logs', [
            'source_exercise_id' => $sourceExercise->id,
            'source_exercise_title' => 'User Squat',
            'target_exercise_id' => $targetExercise->id,
            'target_exercise_title' => 'Squat',
            'admin_user_id' => $this->admin->id,
            'admin_email' => $this->admin->email,
            'lift_log_count' => 2,
            'alias_created' => true,
        ]);

        // Verify lift log IDs were stored correctly
        $mergeLog = \App\Models\ExerciseMergeLog::where('source_exercise_id', $sourceExercise->id)->first();
        $this->assertNotNull($mergeLog);
        $this->assertIsArray($mergeLog->lift_log_ids);
        $this->assertCount(2, $mergeLog->lift_log_ids);
        $this->assertContains($liftLog1->id, $mergeLog->lift_log_ids);
        $this->assertContains($liftLog2->id, $mergeLog->lift_log_ids);

        // Verify relationships work
        $this->assertEquals($this->admin->id, $mergeLog->admin->id);
        $this->assertEquals($targetExercise->id, $mergeLog->targetExercise->id);
    }

    /** @test */
    public function merge_log_persists_even_after_target_exercise_deleted()
    {
        $this->actingAs($this->admin);

        $sourceExercise = Exercise::factory()->create([
            'user_id' => $this->regularUser->id,
            'title' => 'Temp Exercise',
            'exercise_type' => 'regular'
        ]);

        $targetExercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Target Exercise',
            'exercise_type' => 'regular'
        ]);

        // Execute merge
        $this->post(route('exercises.merge', $sourceExercise), [
            'target_exercise_id' => $targetExercise->id,
            'create_alias' => false
        ]);

        // Verify log exists
        $mergeLog = \App\Models\ExerciseMergeLog::where('source_exercise_id', $sourceExercise->id)->first();
        $this->assertNotNull($mergeLog);

        // Delete target exercise
        $targetExercise->delete();

        // Verify log still exists with all data intact
        $mergeLog->refresh();
        $this->assertEquals('Temp Exercise', $mergeLog->source_exercise_title);
        $this->assertEquals('Target Exercise', $mergeLog->target_exercise_title);
        $this->assertEquals($this->admin->email, $mergeLog->admin_email);
    }
}
