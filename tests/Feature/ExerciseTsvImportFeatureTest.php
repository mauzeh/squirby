<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExerciseTsvImportFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Create admin user
        $this->admin = User::factory()->create();
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin->roles()->attach($adminRole);
    }

    public function test_user_can_import_exercises_via_web_interface()
    {
        $tsvData = "Burpees\tFull body bodyweight exercise\ttrue\nDumbbell Rows\tBack exercise with dumbbells\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Burpees',
            'description' => 'Full body bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Dumbbell Rows',
            'description' => 'Back exercise with dumbbells',
            'is_bodyweight' => false,
        ]);
    }

    public function test_user_can_view_exercises_index_with_tsv_export()
    {
        // Create some exercises
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Test Exercise',
            'description' => 'Test Description',
            'is_bodyweight' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('TSV Export');
        $response->assertSee('TSV Import');
        $response->assertSee('Test Exercise');
    }

    public function test_import_with_empty_data_shows_error()
    {
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => ''
            ]);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_with_no_new_data_shows_success_message()
    {
        // Create existing exercise that matches what we'll try to import
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Push Ups',
            'description' => 'Bodyweight exercise',
            'is_bodyweight' => true,
        ]);

        // Try to import the exact same data
        $tsvData = "Push Ups\tBodyweight exercise\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
    }

    public function test_import_with_invalid_data_shows_success_with_no_imports()
    {
        $tsvData = "Invalid\nAnother Invalid Row";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('No new data was imported or updated', $successMessage);
        $this->assertStringContainsString('invalid rows', $successMessage);
    }

    public function test_user_can_import_exercises_with_two_columns_only()
    {
        $tsvData = "Running\tCardio exercise\nSwimming\tFull body cardio";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Running',
            'description' => 'Cardio exercise',
            'is_bodyweight' => false, // Should default to false
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'Swimming',
            'description' => 'Full body cardio',
            'is_bodyweight' => false, // Should default to false
        ]);
    }

    public function test_admin_can_import_global_exercises()
    {
        $tsvData = "Global Burpees\tGlobal full body exercise\ttrue\nGlobal Squats\tGlobal leg exercise\tfalse";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Check that exercises were created as global (user_id = null)
        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Global Burpees',
            'description' => 'Global full body exercise',
            'is_bodyweight' => true,
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Global Squats',
            'description' => 'Global leg exercise',
            'is_bodyweight' => false,
        ]);
    }

    public function test_non_admin_cannot_import_global_exercises()
    {
        $tsvData = "Global Exercise\tShould not be created\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('error', 'Only administrators can import global exercises.');

        // Verify no exercises were created
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Global Exercise'
        ]);
    }

    public function test_import_success_message_shows_detailed_results_for_personal_exercises()
    {
        // Create existing exercise to test update scenario
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Existing Exercise',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "New Exercise\tNew description\ttrue\nExisting Exercise\tUpdated description\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('Imported 1 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• New Exercise (bodyweight)', $successMessage);
        $this->assertStringContainsString('Updated 1 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('• Existing Exercise', $successMessage);
        $this->assertStringContainsString('description: \'Old description\' → \'Updated description\'', $successMessage);
        $this->assertStringContainsString('bodyweight: no → yes', $successMessage);
    }

    public function test_import_success_message_shows_detailed_results_for_global_exercises()
    {
        // Create existing global exercise to test update scenario
        Exercise::create([
            'user_id' => null,
            'title' => 'Existing Global',
            'description' => 'Old global description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "New Global\tNew global description\ttrue\nExisting Global\tUpdated global description\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('TSV data processed successfully!', $successMessage);
        $this->assertStringContainsString('Imported 1 new global exercises:', $successMessage);
        $this->assertStringContainsString('• New Global (bodyweight)', $successMessage);
        $this->assertStringContainsString('Updated 1 existing global exercises:', $successMessage);
        $this->assertStringContainsString('• Existing Global', $successMessage);
        $this->assertStringContainsString('description: \'Old global description\' → \'Updated global description\'', $successMessage);
        $this->assertStringContainsString('bodyweight: no → yes', $successMessage);
    }

    public function test_import_shows_skipped_exercises_when_user_conflicts_with_global()
    {
        // Create a global exercise that will conflict
        Exercise::create([
            'user_id' => null,
            'title' => 'Global Exercise',
            'description' => 'Global description',
            'is_bodyweight' => false,
        ]);

        $tsvData = "Global Exercise\tUser description\ttrue\nUser Exercise\tUser description\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Imported 1 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• User Exercise', $successMessage);
        $this->assertStringContainsString('Skipped 1 exercises:', $successMessage);
        $this->assertStringContainsString('• Global Exercise - Exercise \'Global Exercise\' conflicts with existing global exercise', $successMessage);

        // Verify the global exercise wasn't changed and user exercise was created
        $this->assertDatabaseHas('exercises', [
            'user_id' => null,
            'title' => 'Global Exercise',
            'description' => 'Global description',
        ]);

        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => 'User Exercise',
            'description' => 'User description',
        ]);
    }

    public function test_import_handles_service_exceptions_gracefully()
    {
        // Test with malformed TSV that might cause service to throw exception
        $tsvData = "Valid Exercise\tValid Description\ttrue\n\t\t\t"; // Invalid row that might cause issues

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        // Should either succeed or show a proper error message, not crash
        $this->assertTrue(
            session()->has('success') || session()->has('error'),
            'Response should have either success or error message'
        );
    }

    public function test_import_validation_requires_tsv_data()
    {
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'import_as_global' => false
            ]);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_validation_accepts_boolean_import_as_global()
    {
        $tsvData = "Test Exercise\tTest Description\tfalse";

        // Test with boolean true
        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');

        // Test with boolean false (default)
        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => "Another Exercise\tAnother Description\tfalse",
                'import_as_global' => false
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
    }

    public function test_admin_can_see_global_import_option_in_view()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertSee('Import as Global Exercises (available to all users)');
        $response->assertSee('import_as_global');
        $response->assertSee('Global exercises will be available to all users and can only be managed by administrators.');
    }

    public function test_regular_user_cannot_see_global_import_option_in_view()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Import as Global Exercises (available to all users)');
        $response->assertDontSee('import_as_global');
        $response->assertDontSee('Global exercises will be available to all users and can only be managed by administrators.');
        
        // But should still see the regular import form
        $response->assertSee('TSV Import');
        $response->assertSee('Import Exercises');
    }

    public function test_view_shows_proper_form_structure_for_admin()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        
        // Check that the form has proper structure
        $response->assertSee('TSV Data:');
        $response->assertSee('placeholder="Exercise Name&#9;Description&#9;Is Bodyweight (true/false)"', false);
        $response->assertSee('Import as Global Exercises (available to all users)');
        $response->assertSee('Personal exercises are only visible to you and will be skipped if they conflict with existing global exercises.');
        $response->assertSee('Import Exercises');
    }

    public function test_view_shows_proper_form_structure_for_regular_user()
    {
        $response = $this->actingAs($this->user)
            ->get(route('exercises.index'));

        $response->assertStatus(200);
        
        // Check that the form has proper structure but without global option
        $response->assertSee('TSV Data:');
        $response->assertSee('placeholder="Exercise Name&#9;Description&#9;Is Bodyweight (true/false)"', false);
        $response->assertSee('Import Exercises');
        
        // Should not see admin-specific content
        $response->assertDontSee('Import as Global Exercises');
        $response->assertDontSee('Personal exercises are only visible to you and will be skipped if they conflict with existing global exercises.');
    }

    public function test_import_uses_case_insensitive_matching_for_conflicts()
    {
        // Create a global exercise with mixed case
        Exercise::create([
            'user_id' => null,
            'title' => 'Push Ups',
            'description' => 'Global description',
            'is_bodyweight' => true,
        ]);

        // Try to import with different case variations
        $tsvData = "push ups\tUser description\tfalse\nPUSH UPS\tAnother description\ttrue\nPuSh UpS\tThird description\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Skipped 3 exercises:', $successMessage);
        $this->assertStringContainsString('• push ups - Exercise \'push ups\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• PUSH UPS - Exercise \'PUSH UPS\' conflicts with existing global exercise', $successMessage);
        $this->assertStringContainsString('• PuSh UpS - Exercise \'PuSh UpS\' conflicts with existing global exercise', $successMessage);

        // Verify no new exercises were created
        $this->assertEquals(1, Exercise::where('title', 'Push Ups')->count());
    }

    public function test_global_import_uses_case_insensitive_matching_for_updates()
    {
        // Create a global exercise with mixed case
        Exercise::create([
            'user_id' => null,
            'title' => 'Squats',
            'description' => 'Original description',
            'is_bodyweight' => false,
        ]);

        // Try to import with different case - should update existing
        $tsvData = "SQUATS\tUpdated description\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Updated 1 existing global exercises:', $successMessage);
        $this->assertStringContainsString('• Squats', $successMessage);
        $this->assertStringContainsString('description: \'Original description\' → \'Updated description\'', $successMessage);
        $this->assertStringContainsString('bodyweight: no → yes', $successMessage);

        // Verify the exercise was updated, not duplicated
        $this->assertEquals(1, Exercise::whereRaw('LOWER(title) = ?', ['squats'])->count());
        $exercise = Exercise::where('title', 'Squats')->first();
        $this->assertEquals('Updated description', $exercise->description);
        $this->assertTrue($exercise->is_bodyweight);
    }

    public function test_user_import_uses_case_insensitive_matching_for_personal_exercises()
    {
        // Create a personal exercise
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Deadlifts',
            'description' => 'Original description',
            'is_bodyweight' => false,
        ]);

        // Try to import with different case - should update existing
        $tsvData = "deadlifts\tUpdated description\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Updated 1 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('• Deadlifts', $successMessage);

        // Verify the exercise was updated, not duplicated
        $this->assertEquals(1, Exercise::where('user_id', $this->user->id)->whereRaw('LOWER(title) = ?', ['deadlifts'])->count());
        $exercise = Exercise::where('user_id', $this->user->id)->where('title', 'Deadlifts')->first();
        $this->assertEquals('Updated description', $exercise->description);
        $this->assertTrue($exercise->is_bodyweight);
    }

    public function test_import_only_updates_when_data_actually_differs()
    {
        // Create existing exercise with specific data
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
            'description' => 'Chest exercise',
            'is_bodyweight' => false,
        ]);

        // Try to import exact same data
        $tsvData = "Bench Press\tChest exercise\tfalse";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Skipped 1 exercises:', $successMessage);
        $this->assertStringContainsString('• Bench Press - Personal exercise \'Bench Press\' already exists with same data', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
    }

    public function test_global_import_only_updates_when_data_actually_differs()
    {
        // Create existing global exercise with specific data
        Exercise::create([
            'user_id' => null,
            'title' => 'Pull Ups',
            'description' => 'Back exercise',
            'is_bodyweight' => true,
        ]);

        // Try to import exact same data
        $tsvData = "Pull Ups\tBack exercise\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        $this->assertStringContainsString('Skipped 1 exercises:', $successMessage);
        $this->assertStringContainsString('• Pull Ups - Global exercise \'Pull Ups\' already exists with same data', $successMessage);
        $this->assertStringContainsString('No new data was imported or updated - all entries already exist with the same data.', $successMessage);
    }

    public function test_import_provides_detailed_summary_with_mixed_results()
    {
        // Create existing exercises for various scenarios
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Existing Personal',
            'description' => 'Old description',
            'is_bodyweight' => false,
        ]);

        Exercise::create([
            'user_id' => null,
            'title' => 'Existing Global',
            'description' => 'Global description',
            'is_bodyweight' => true,
        ]);

        // Import data with new, update, and conflict scenarios
        $tsvData = "New Exercise\tNew description\ttrue\n" .
                   "Existing Personal\tUpdated description\ttrue\n" .
                   "Existing Global\tShould be skipped\tfalse\n" .
                   "Another New\tAnother description\tfalse\n" .
                   "\tMissing name\tfalse\n"; // Invalid row - missing exercise name

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('success');
        
        $successMessage = session('success');
        
        // Should show imported exercises
        $this->assertStringContainsString('Imported 2 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• New Exercise (bodyweight)', $successMessage);
        $this->assertStringContainsString('• Another New', $successMessage);
        
        // Should show updated exercises
        $this->assertStringContainsString('Updated 1 existing personal exercises:', $successMessage);
        $this->assertStringContainsString('• Existing Personal', $successMessage);
        
        // Should show skipped exercises
        $this->assertStringContainsString('Skipped', $successMessage);
        $this->assertStringContainsString('• Existing Global - Exercise \'Existing Global\' conflicts with existing global exercise', $successMessage);
        
        // Should mention invalid rows
        $this->assertStringContainsString('Found 1 invalid rows that were skipped.', $successMessage);
    }

    public function test_import_error_messages_are_specific_for_different_failure_modes()
    {
        // Test permission error for non-admin trying global import
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => 'Test\tDescription\tfalse',
                'import_as_global' => true
            ]);

        $response->assertRedirect(route('exercises.index'));
        $response->assertSessionHas('error', 'Only administrators can import global exercises.');

        // Test empty data error
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => ''
            ]);

        $response->assertSessionHasErrors(['tsv_data']);

        // Test validation error for missing tsv_data
        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), []);

        $response->assertSessionHasErrors(['tsv_data']);
    }

    public function test_import_results_clearly_indicate_global_vs_personal_exercise_types()
    {
        // Test personal import result indication
        $tsvData = "Personal Exercise\tPersonal description\ttrue";

        $response = $this->actingAs($this->user)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData
            ]);

        $successMessage = session('success');
        $this->assertStringContainsString('Imported 1 new personal exercises:', $successMessage);
        $this->assertStringContainsString('• Personal Exercise (bodyweight)', $successMessage);

        // Test global import result indication
        $tsvData = "Global Exercise\tGlobal description\ttrue";

        $response = $this->actingAs($this->admin)
            ->post(route('exercises.import-tsv'), [
                'tsv_data' => $tsvData,
                'import_as_global' => true
            ]);

        $successMessage = session('success');
        $this->assertStringContainsString('Imported 1 new global exercises:', $successMessage);
        $this->assertStringContainsString('• Global Exercise (bodyweight)', $successMessage);
    }
}