<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use App\Models\MobileLiftForm;
use App\Services\MobileEntry\LiftLogService;
use App\Services\TrainingProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ShowExtraWeightPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $bodyweightExercise;
    private Exercise $weightedExercise;
    private LiftLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'show_extra_weight' => false
        ]);
        
        $this->bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
        ]);
        
        $this->weightedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
            'exercise_type' => 'regular'
        ]);
        
        // Use real services for this test since we're testing form generation with database
        $this->service = app(LiftLogService::class);
    }

    public function test_bodyweight_exercise_hides_weight_field_when_preference_disabled()
    {
        // Create a mobile lift form with bodyweight exercise (user has show_extra_weight = false by default)
        $mobileLiftForm = MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => Carbon::today(),
        ]);

        $forms = $this->service->generateForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is NOT present
        $weightField = collect($forms[0]['data']['numericFields'])->firstWhere('name', 'weight');
        $this->assertNull($weightField, 'Weight field should be hidden when show_extra_weight is false for bodyweight exercises');
        
        // But reps and rounds fields should still be present
        $repsField = collect($forms[0]['data']['numericFields'])->firstWhere('name', 'reps');
        $roundsField = collect($forms[0]['data']['numericFields'])->firstWhere('name', 'rounds');
        $this->assertNotNull($repsField);
        $this->assertNotNull($roundsField);
    }

    public function test_bodyweight_exercise_shows_weight_field_when_preference_enabled()
    {
        // Enable show extra weight preference
        $this->user->update(['show_extra_weight' => true]);
        
        // Create a mobile lift form with bodyweight exercise
        $mobileLiftForm = MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => Carbon::today(),
        ]);

        $forms = $this->service->generateForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is present
        $weightField = collect($forms[0]['data']['numericFields'])->firstWhere('name', 'weight');
        $this->assertNotNull($weightField, 'Weight field should be present when show_extra_weight is true');
        $this->assertEquals('Added Weight (lbs):', $weightField['label']);
    }

    public function test_weighted_exercise_always_shows_weight_field_regardless_of_preference()
    {
        // Disable show extra weight preference
        $this->user->update(['show_extra_weight' => false]);
        
        // Create a mobile lift form with weighted exercise
        $mobileLiftForm = MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->weightedExercise->id,
            'date' => Carbon::today(),
        ]);

        $forms = $this->service->generateForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is present even with preference disabled
        $weightField = collect($forms[0]['data']['numericFields'])->firstWhere('name', 'weight');
        $this->assertNotNull($weightField, 'Weight field should always be present for weighted exercises');
        $this->assertEquals('Weight (lbs):', $weightField['label']);
    }

    public function test_profile_preferences_form_updates_show_extra_weight_setting()
    {
        $this->actingAs($this->user);

        $response = $this->patch(route('profile.update-preferences'), [
            'show_extra_weight' => true
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'preferences-updated');
        
        $this->user->refresh();
        $this->assertTrue($this->user->show_extra_weight);
    }

    public function test_bodyweight_exercise_can_be_logged_without_weight_when_preference_disabled()
    {
        $this->actingAs($this->user);
        
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => now()->toDateString(),
            'reps' => 10,
            'rounds' => 3,
            'comments' => 'Test workout',
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('lift_logs', [
            'exercise_id' => $this->bodyweightExercise->id,
            'user_id' => $this->user->id,
        ]);
        
        $this->assertDatabaseHas('lift_sets', [
            'weight' => 0,
            'reps' => 10,
        ]);
    }

    public function test_bodyweight_exercise_requires_weight_when_preference_enabled()
    {
        $this->user->update(['show_extra_weight' => true]);
        $this->actingAs($this->user);
        
        // Test without weight - should fail validation
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => now()->toDateString(),
            'reps' => 10,
            'rounds' => 3,
            'comments' => 'Test workout',
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertSessionHasErrors(['weight']);
        
        // Test with weight - should succeed
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => now()->toDateString(),
            'reps' => 10,
            'rounds' => 3,
            'weight' => 25,
            'comments' => 'Test workout',
            'redirect_to' => 'mobile-entry-lifts'
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('lift_sets', [
            'weight' => 25,
            'reps' => 10,
        ]);
    }
}