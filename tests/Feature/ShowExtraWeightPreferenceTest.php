<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Services\MobileEntryLiftLogFormService;
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
    private MobileEntryLiftLogFormService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'show_extra_weight' => false
        ]);
        
        $this->bodyweightExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Push-ups',
            'is_bodyweight' => true
        ]);
        
        $this->weightedExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press',
            'is_bodyweight' => false
        ]);
        
        $mockProgressionService = $this->createMock(TrainingProgressionService::class);
        $mockProgressionService->method('getSuggestionDetails')->willReturn(null);
        
        $this->service = new MobileEntryLiftLogFormService($mockProgressionService);
    }

    public function test_bodyweight_exercise_hides_weight_field_when_preference_disabled()
    {
        // Create a program with bodyweight exercise (user has show_extra_weight = false by default)
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => Carbon::today(),
            'reps' => 10,
            'sets' => 3
        ]);

        $forms = $this->service->generateProgramForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is NOT present
        $weightField = collect($forms[0]['numericFields'])->firstWhere('name', 'weight');
        $this->assertNull($weightField, 'Weight field should be hidden when show_extra_weight is false for bodyweight exercises');
        
        // But reps and rounds fields should still be present
        $repsField = collect($forms[0]['numericFields'])->firstWhere('name', 'reps');
        $roundsField = collect($forms[0]['numericFields'])->firstWhere('name', 'rounds');
        $this->assertNotNull($repsField);
        $this->assertNotNull($roundsField);
    }

    public function test_bodyweight_exercise_shows_weight_field_when_preference_enabled()
    {
        // Enable show extra weight preference
        $this->user->update(['show_extra_weight' => true]);
        
        // Create a program with bodyweight exercise
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->bodyweightExercise->id,
            'date' => Carbon::today(),
            'reps' => 10,
            'sets' => 3
        ]);

        $forms = $this->service->generateProgramForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is present
        $weightField = collect($forms[0]['numericFields'])->firstWhere('name', 'weight');
        $this->assertNotNull($weightField, 'Weight field should be present when show_extra_weight is true');
        $this->assertEquals('Added Weight (lbs):', $weightField['label']);
    }

    public function test_weighted_exercise_always_shows_weight_field_regardless_of_preference()
    {
        // Disable show extra weight preference
        $this->user->update(['show_extra_weight' => false]);
        
        // Create a program with weighted exercise
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->weightedExercise->id,
            'date' => Carbon::today(),
            'reps' => 5,
            'sets' => 3
        ]);

        $forms = $this->service->generateProgramForms($this->user->id, Carbon::today());

        $this->assertCount(1, $forms);
        
        // Check that weight field is present even with preference disabled
        $weightField = collect($forms[0]['numericFields'])->firstWhere('name', 'weight');
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
}