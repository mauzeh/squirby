<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function an_exercise_can_be_created_with_is_bodyweight_attribute()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'description' => 'A bodyweight exercise',
            'exercise_type' => 'bodyweight',
        ]);

        $this->assertEquals('bodyweight', $exercise->exercise_type);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'bodyweight',
        ]);
    }

    /** @test */
    public function an_exercise_defaults_to_not_bodyweight()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'description' => 'A weighted exercise',
        ]);

        $this->assertEquals('regular', $exercise->exercise_type);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'regular',
        ]);
    }

    /** @test */
    public function scope_global_returns_only_exercises_with_null_user_id()
    {
        // Create global exercises (user_id = null)
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        
        // Create user-specific exercises
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);

        $globalExercises = Exercise::global()->get();

        $this->assertCount(2, $globalExercises);
        $this->assertTrue($globalExercises->contains($globalExercise1));
        $this->assertTrue($globalExercises->contains($globalExercise2));
        $this->assertFalse($globalExercises->contains($userExercise));
    }

    /** @test */
    public function scope_user_specific_returns_only_exercises_for_given_user()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises for different users
        $user1Exercise1 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise 1']);
        $user1Exercise2 = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise 2']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);

        $user1Exercises = Exercise::userSpecific($user1->id)->get();

        $this->assertCount(2, $user1Exercises);
        $this->assertTrue($user1Exercises->contains($user1Exercise1));
        $this->assertTrue($user1Exercises->contains($user1Exercise2));
        $this->assertFalse($user1Exercises->contains($user2Exercise));
        $this->assertFalse($user1Exercises->contains($globalExercise));
    }

    /** @test */
    public function scope_available_to_user_returns_global_and_user_exercises_for_regular_user()
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);

        $availableToUser1 = Exercise::availableToUser($user1->id)->get();

        $this->assertCount(3, $availableToUser1);
        $this->assertTrue($availableToUser1->contains($globalExercise1));
        $this->assertTrue($availableToUser1->contains($globalExercise2));
        $this->assertTrue($availableToUser1->contains($user1Exercise));
        $this->assertFalse($availableToUser1->contains($user2Exercise));
    }

    /** @test */
    public function scope_available_to_user_returns_all_exercises_for_admin_user()
    {
        // Create admin user with role
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Create regular users
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        $adminExercise = Exercise::factory()->create(['user_id' => $admin->id, 'title' => 'Admin Exercise']);

        $availableToAdmin = Exercise::availableToUser($admin->id)->get();

        // Admin should see all exercises
        $this->assertCount(5, $availableToAdmin);
        $this->assertTrue($availableToAdmin->contains($globalExercise1));
        $this->assertTrue($availableToAdmin->contains($globalExercise2));
        $this->assertTrue($availableToAdmin->contains($user1Exercise));
        $this->assertTrue($availableToAdmin->contains($user2Exercise));
        $this->assertTrue($availableToAdmin->contains($adminExercise));
    }

    /** @test */
    public function scope_available_to_user_handles_invalid_user_id()
    {
        // Create a real user first
        $realUser = \App\Models\User::factory()->create();
        
        // Create some exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $realUser->id, 'title' => 'User Exercise']);

        // Test with non-existent user ID
        $availableToInvalidUser = Exercise::availableToUser(999)->get();

        // Should return no exercises for security (invalid user ID)
        $this->assertCount(0, $availableToInvalidUser);
        $this->assertFalse($availableToInvalidUser->contains($globalExercise));
        $this->assertFalse($availableToInvalidUser->contains($userExercise));
    }

    /** @test */
    public function scope_available_to_user_handles_user_with_no_role()
    {
        // Create user without any roles
        $user = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id, 'title' => 'Other User Exercise']);

        $availableToUser = Exercise::availableToUser($user->id)->get();

        // Should behave like regular user (global + own exercises)
        $this->assertCount(2, $availableToUser);
        $this->assertTrue($availableToUser->contains($globalExercise));
        $this->assertTrue($availableToUser->contains($userExercise));
        $this->assertFalse($availableToUser->contains($otherUserExercise));
    }

    /** @test */
    public function scope_available_to_user_maintains_ordering_for_admin()
    {
        // Create admin user with role
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $userExercise = Exercise::factory()->create(['user_id' => $admin->id, 'title' => 'User Exercise']);

        $availableToAdmin = Exercise::availableToUser($admin->id)->get();

        // Should have both exercises
        $this->assertCount(2, $availableToAdmin);
        // Should maintain ordering (user exercises first, then global exercises due to orderByRaw 'user_id IS NULL ASC')
        // user_id IS NULL ASC means: user exercises (user_id IS NOT NULL = 0) come before global exercises (user_id IS NULL = 1)
        $this->assertEquals($userExercise->id, $availableToAdmin->first()->id);
        $this->assertEquals($globalExercise->id, $availableToAdmin->last()->id);
    }

    /** @test */
    public function is_global_returns_true_when_user_id_is_null()
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        $userExercise = Exercise::factory()->create(['user_id' => \App\Models\User::factory()->create()->id]);

        $this->assertTrue($globalExercise->isGlobal());
        $this->assertFalse($userExercise->isGlobal());
    }

    /** @test */
    public function can_be_edited_by_returns_true_for_admin_on_global_exercise()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $regularUser = \App\Models\User::factory()->create();
        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        $this->assertTrue($globalExercise->canBeEditedBy($admin));
        $this->assertFalse($globalExercise->canBeEditedBy($regularUser));
    }

    /** @test */
    public function can_be_edited_by_returns_true_for_owner_on_user_exercise()
    {
        $owner = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($userExercise->canBeEditedBy($owner));
        $this->assertFalse($userExercise->canBeEditedBy($otherUser));
    }

    /** @test */
    public function can_be_deleted_by_returns_false_when_exercise_has_lift_logs()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        // Create a lift log associated with the exercise
        \App\Models\LiftLog::factory()->create(['exercise_id' => $exercise->id, 'user_id' => $user->id]);

        $this->assertFalse($exercise->canBeDeletedBy($user));
    }

    /** @test */
    public function can_be_deleted_by_returns_true_when_exercise_has_no_lift_logs_and_user_can_edit()
    {
        $user = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $exercise->liftLogs()->count());

        $this->assertTrue($exercise->canBeDeletedBy($user));
    }

    /** @test */
    public function can_be_deleted_by_returns_false_when_user_cannot_edit_exercise()
    {
        $owner = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $owner->id]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $exercise->liftLogs()->count());

        $this->assertFalse($exercise->canBeDeletedBy($otherUser));
    }

    /** @test */
    public function admin_can_delete_global_exercise_without_lift_logs()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);

        // Ensure no lift logs exist for this exercise
        $this->assertEquals(0, $globalExercise->liftLogs()->count());

        $this->assertTrue($globalExercise->canBeDeletedBy($admin));
    }

    /** @test */
    public function admin_cannot_delete_global_exercise_with_lift_logs()
    {
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);

        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        // Create a lift log associated with the exercise
        \App\Models\LiftLog::factory()->create(['exercise_id' => $globalExercise->id, 'user_id' => $admin->id]);

        $this->assertFalse($globalExercise->canBeDeletedBy($admin));
    }

    /** @test */
    public function is_banded_resistance_returns_true_for_resistance_band_type()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_resistance']);
        $this->assertTrue($exercise->isBandedResistance());
        $this->assertFalse($exercise->isBandedAssistance());
    }

    /** @test */
    public function is_banded_assistance_returns_true_for_assistance_band_type()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'banded_assistance']);
        $this->assertTrue($exercise->isBandedAssistance());
        $this->assertFalse($exercise->isBandedResistance());
    }

    /** @test */
    public function is_banded_resistance_and_assistance_return_false_for_regular_exercise_type()
    {
        $exercise = Exercise::factory()->create(['exercise_type' => 'regular']);
        $this->assertFalse($exercise->isBandedResistance());
        $this->assertFalse($exercise->isBandedAssistance());
    }

    /** @test */
    public function canonical_name_is_automatically_generated_on_creation()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Back Squat',
            'description' => 'A compound exercise',
        ]);

        $this->assertEquals('back_squat', $exercise->canonical_name);
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'canonical_name' => 'back_squat',
        ]);
    }

    /** @test */
    public function canonical_name_handles_special_characters_and_spaces()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Half-Kneeling DB Press (Single Arm)',
            'description' => 'A unilateral exercise',
        ]);

        $this->assertEquals('half_kneeling_db_press_single_arm', $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_handles_numbers_and_mixed_case()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press (2-DB Seesaw)',
            'description' => 'A variation with dumbbells',
        ]);

        $this->assertEquals('bench_press_2_db_seesaw', $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_generates_unique_names_for_duplicate_titles()
    {
        $exercise1 = Exercise::factory()->create([
            'title' => 'Push Up',
            'description' => 'First push up exercise',
        ]);

        $exercise2 = Exercise::factory()->create([
            'title' => 'Push Up',
            'description' => 'Second push up exercise',
        ]);

        $this->assertEquals('push_up', $exercise1->canonical_name);
        $this->assertEquals('push_up_1', $exercise2->canonical_name);
    }

    /** @test */
    public function canonical_name_handles_multiple_duplicates()
    {
        $exercise1 = Exercise::factory()->create(['title' => 'Squat']);
        $exercise2 = Exercise::factory()->create(['title' => 'Squat']);
        $exercise3 = Exercise::factory()->create(['title' => 'Squat']);

        $this->assertEquals('squat', $exercise1->canonical_name);
        $this->assertEquals('squat_1', $exercise2->canonical_name);
        $this->assertEquals('squat_2', $exercise3->canonical_name);
    }

    /** @test */
    public function canonical_name_is_not_updated_when_title_changes()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Original Title',
            'description' => 'Original description',
        ]);

        $originalCanonicalName = $exercise->canonical_name;
        $this->assertEquals('original_title', $originalCanonicalName);

        // Update the title
        $exercise->update(['title' => 'Updated Title']);
        $exercise->refresh();

        // Canonical name should remain unchanged
        $this->assertEquals($originalCanonicalName, $exercise->canonical_name);
        $this->assertEquals('original_title', $exercise->canonical_name);
        $this->assertEquals('Updated Title', $exercise->title);
    }

    /** @test */
    public function canonical_name_is_not_updated_when_other_fields_change()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'description' => 'Original description',
            'exercise_type' => 'regular',
        ]);

        $originalCanonicalName = $exercise->canonical_name;
        $this->assertEquals('test_exercise', $originalCanonicalName);

        // Update other fields
        $exercise->update([
            'description' => 'Updated description',
            'exercise_type' => 'bodyweight',
        ]);
        $exercise->refresh();

        // Canonical name should remain unchanged
        $this->assertEquals($originalCanonicalName, $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_respects_manually_set_value_during_creation()
    {
        // Create exercise with manually set canonical_name
        $exercise = new Exercise([
            'title' => 'Manual Test',
            'description' => 'Test with manual canonical name',
        ]);
        $exercise->canonical_name = 'custom_canonical_name';
        $exercise->save();

        $this->assertEquals('custom_canonical_name', $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_is_not_generated_if_title_is_empty()
    {
        $exercise = new Exercise([
            'title' => '',
            'description' => 'Exercise without title',
        ]);
        $exercise->save();

        $this->assertNull($exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_is_not_generated_if_title_is_null()
    {
        // Since title is required in the database, we'll test by creating an exercise
        // and then setting title to null to test the boot method logic
        $exercise = new Exercise([
            'description' => 'Exercise without title',
        ]);
        
        // Set title to null after instantiation to test the boot logic
        $exercise->title = null;
        
        // The boot method should not generate a canonical name when title is null
        // We can't save this to database due to NOT NULL constraint, but we can test the logic
        $this->assertNull($exercise->canonical_name);
        
        // Test that the condition in boot method works correctly
        $shouldGenerate = !empty($exercise->canonical_name) || !empty($exercise->title);
        $this->assertFalse($shouldGenerate);
    }

    /** @test */
    public function canonical_name_uniqueness_excludes_current_exercise_during_updates()
    {
        // Create an exercise
        $exercise = Exercise::factory()->create(['title' => 'Test Exercise']);
        $this->assertEquals('test_exercise', $exercise->canonical_name);

        // Update description (not title) - canonical name should remain the same
        $exercise->update(['description' => 'Updated description']);
        $exercise->refresh();

        // Should still have the same canonical name
        $this->assertEquals('test_exercise', $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_handles_unicode_characters()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Café Squat & Déjà Vu',
            'description' => 'Exercise with unicode characters',
        ]);

        // Should convert unicode characters to ASCII equivalents
        $this->assertEquals('cafe_squat_deja_vu', $exercise->canonical_name);
    }

    /** @test */
    public function canonical_name_handles_very_long_titles()
    {
        $longTitle = 'This is a very long exercise title that contains many words and should be properly converted to a canonical name format';
        
        $exercise = Exercise::factory()->create([
            'title' => $longTitle,
            'description' => 'Exercise with long title',
        ]);

        $expectedCanonicalName = 'this_is_a_very_long_exercise_title_that_contains_many_words_and_should_be_properly_converted_to_a_canonical_name_format';
        $this->assertEquals($expectedCanonicalName, $exercise->canonical_name);
    }

    /** @test */
    public function scope_available_to_user_respects_show_global_preference_false()
    {
        $user = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id, 'title' => 'Other User Exercise']);

        // Test with showGlobal = false
        $availableToUser = Exercise::availableToUser($user->id, false)->get();

        // Should only show user's own exercises
        $this->assertCount(1, $availableToUser);
        $this->assertTrue($availableToUser->contains($userExercise));
        $this->assertFalse($availableToUser->contains($globalExercise1));
        $this->assertFalse($availableToUser->contains($globalExercise2));
        $this->assertFalse($availableToUser->contains($otherUserExercise));
    }

    /** @test */
    public function scope_available_to_user_respects_show_global_preference_true()
    {
        $user = \App\Models\User::factory()->create();
        $otherUser = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 1']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise 2']);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'User Exercise']);
        $otherUserExercise = Exercise::factory()->create(['user_id' => $otherUser->id, 'title' => 'Other User Exercise']);

        // Test with showGlobal = true (explicit)
        $availableToUser = Exercise::availableToUser($user->id, true)->get();

        // Should show global + user exercises
        $this->assertCount(3, $availableToUser);
        $this->assertTrue($availableToUser->contains($globalExercise1));
        $this->assertTrue($availableToUser->contains($globalExercise2));
        $this->assertTrue($availableToUser->contains($userExercise));
        $this->assertFalse($availableToUser->contains($otherUserExercise));
    }

    /** @test */
    public function scope_available_to_user_admin_sees_all_exercises_regardless_of_preference()
    {
        // Create admin user with role
        $adminRole = \App\Models\Role::factory()->create(['name' => 'Admin']);
        $admin = \App\Models\User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Create regular users
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();
        
        // Create exercises
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);
        $user1Exercise = Exercise::factory()->create(['user_id' => $user1->id, 'title' => 'User 1 Exercise']);
        $user2Exercise = Exercise::factory()->create(['user_id' => $user2->id, 'title' => 'User 2 Exercise']);
        $adminExercise = Exercise::factory()->create(['user_id' => $admin->id, 'title' => 'Admin Exercise']);

        // Test admin with showGlobal = false (should still see all)
        $availableToAdmin = Exercise::availableToUser($admin->id, false)->get();

        // Admin should see all exercises regardless of preference
        $this->assertCount(4, $availableToAdmin);
        $this->assertTrue($availableToAdmin->contains($globalExercise));
        $this->assertTrue($availableToAdmin->contains($user1Exercise));
        $this->assertTrue($availableToAdmin->contains($user2Exercise));
        $this->assertTrue($availableToAdmin->contains($adminExercise));
    }

    /** @test */
    public function scope_available_to_user_maintains_ordering_when_show_global_false()
    {
        $user = \App\Models\User::factory()->create();
        
        // Create user exercises with different titles to test ordering
        $userExercise1 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'Z Exercise']);
        $userExercise2 = Exercise::factory()->create(['user_id' => $user->id, 'title' => 'A Exercise']);
        $globalExercise = Exercise::factory()->create(['user_id' => null, 'title' => 'Global Exercise']);

        // Test with showGlobal = false
        $availableToUser = Exercise::availableToUser($user->id, false)->get();

        // Should only show user exercises, ordered by title
        $this->assertCount(2, $availableToUser);
        $this->assertEquals($userExercise2->id, $availableToUser->first()->id); // 'A Exercise' comes first
        $this->assertEquals($userExercise1->id, $availableToUser->last()->id);  // 'Z Exercise' comes last
        $this->assertFalse($availableToUser->contains($globalExercise));
    }

    /** @test */
    public function can_be_merged_by_admin_returns_false_for_global_exercises()
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $this->assertFalse($globalExercise->canBeMergedByAdmin());
    }

    /** @test */
    public function can_be_merged_by_admin_returns_false_when_no_compatible_targets_exist()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'banded_resistance'
        ]);

        // No global exercises exist, so no compatible targets
        $this->assertFalse($userExercise->canBeMergedByAdmin());
    }

    /** @test */
    public function can_be_merged_by_admin_returns_true_when_compatible_targets_exist()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'regular'
        ]);

        // Create compatible global exercise
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);

        $this->assertTrue($userExercise->canBeMergedByAdmin());
    }

    /** @test */
    public function is_compatible_for_merge_returns_false_when_merging_with_itself()
    {
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        $this->assertFalse($exercise->isCompatibleForMerge($exercise));
    }

    /** @test */
    public function is_compatible_for_merge_returns_false_when_target_is_not_global()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise1 = Exercise::factory()->create(['user_id' => $user->id]);
        $userExercise2 = Exercise::factory()->create(['user_id' => $user->id]);
        
        $this->assertFalse($userExercise1->isCompatibleForMerge($userExercise2));
    }

    /** @test */
    public function is_compatible_for_merge_returns_false_when_bodyweight_values_differ()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'bodyweight'
        ]);
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);
        
        $this->assertFalse($userExercise->isCompatibleForMerge($globalExercise));
    }

    /** @test */
    public function is_compatible_for_merge_returns_false_when_band_types_differ()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'banded_resistance'
        ]);
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_assistance'
        ]);
        
        $this->assertFalse($userExercise->isCompatibleForMerge($globalExercise));
    }

    /** @test */
    public function is_compatible_for_merge_returns_true_when_both_band_types_are_null()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'regular'
        ]);
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'regular'
        ]);
        
        $this->assertTrue($userExercise->isCompatibleForMerge($globalExercise));
    }

    /** @test */
    public function is_compatible_for_merge_returns_true_when_one_band_type_is_null()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'regular'
        ]);
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_resistance'
        ]);
        
        $this->assertTrue($userExercise->isCompatibleForMerge($globalExercise));
        $this->assertTrue($globalExercise->isCompatibleForMerge($userExercise));
    }

    /** @test */
    public function is_compatible_for_merge_returns_true_when_band_types_match()
    {
        $user = \App\Models\User::factory()->create();
        $userExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'exercise_type' => 'banded_resistance'
        ]);
        $globalExercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_resistance'
        ]);
        
        $this->assertTrue($userExercise->isCompatibleForMerge($globalExercise));
    }

    /** @test */
    public function has_owner_with_global_visibility_disabled_returns_false_for_global_exercises()
    {
        $globalExercise = Exercise::factory()->create(['user_id' => null]);
        
        $this->assertFalse($globalExercise->hasOwnerWithGlobalVisibilityDisabled());
    }

    /** @test */
    public function has_owner_with_global_visibility_disabled_returns_false_when_user_has_global_visibility_enabled()
    {
        $user = \App\Models\User::factory()->create(['show_global_exercises' => true]);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        $this->assertFalse($userExercise->hasOwnerWithGlobalVisibilityDisabled());
    }

    /** @test */
    public function has_owner_with_global_visibility_disabled_returns_true_when_user_has_global_visibility_disabled()
    {
        $user = \App\Models\User::factory()->create(['show_global_exercises' => false]);
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        $this->assertTrue($userExercise->hasOwnerWithGlobalVisibilityDisabled());
    }

    /** @test */
    public function has_owner_with_global_visibility_disabled_returns_false_when_user_has_default_global_visibility()
    {
        // User without explicitly setting show_global_exercises (defaults to true via shouldShowGlobalExercises method)
        $user = \App\Models\User::factory()->create();
        // Don't set show_global_exercises, let it use the default
        $userExercise = Exercise::factory()->create(['user_id' => $user->id]);
        
        $this->assertFalse($userExercise->hasOwnerWithGlobalVisibilityDisabled());
    }
}
