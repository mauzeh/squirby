<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\ExercisePRService;
use App\Services\MobileEntry\LiftProgressionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KilogramsSupportIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'weight_unit' => 'lbs',
            'prefill_suggested_values' => true,
        ]);
        $this->exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
    }

    /** @test */
    public function it_integrates_full_kilograms_support_flow_end_to_end(): void
    {
        $this->actingAs($this->user);

        // 1. User starts with default weight_unit = 'lbs'
        $this->assertEquals('lbs', $this->user->weight_unit);

        // 2. User logs a lift (200 lbs x 5 reps) - 4 days ago
        $date1 = now()->subDays(4)->format('Y-m-d');
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $this->exercise->id,
            'weight' => 200,
            'reps' => 5,
            'rounds' => 3,
            'date' => $date1,
            'logged_at' => '12:00',
        ]);

        $response->assertSessionHasNoErrors();

        // Assert DB has lift sets logged in lbs
        $log1 = LiftLog::where('exercise_id', $this->exercise->id)->orderBy('logged_at', 'asc')->first();
        $this->assertNotNull($log1);
        $this->assertEquals(3, $log1->liftSets()->count());
        $this->assertEquals('lbs', $log1->liftSets->first()->unit);
        $this->assertEquals(200.0, $log1->liftSets->first()->weight);

        // 3. User switches preference to 'kg' via profile update
        $response = $this->patch(route('profile.update-preferences'), [
            'weight_unit' => 'kg',
            'prefill_suggested_values' => true,
        ]);
        $response->assertRedirect(route('profile.edit-preferences'));
        $this->user->refresh();
        $this->assertEquals('kg', $this->user->weight_unit);
        $this->actingAs($this->user);

        // 4. User views previous lift log -> assert display shows ~91 kg (specifically 90.5 kg)
        $strategy = $this->exercise->getTypeStrategy();
        $formattedDisplay = $strategy->formatWeightDisplay($log1);
        $this->assertEquals('90.5 kg', $formattedDisplay);

        // 5. User logs a new lift (100 kg x 5 reps) - 2 days ago
        $date2 = now()->subDays(2)->format('Y-m-d');
        $response = $this->post(route('lift-logs.store'), [
            'exercise_id' => $this->exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'date' => $date2,
            'logged_at' => '12:00',
        ]);
        $response->assertSessionHasNoErrors();

        // Assert DB has lift sets logged in kg
        $log2 = LiftLog::where('exercise_id', $this->exercise->id)->orderBy('logged_at', 'desc')->first();
        $this->assertNotNull($log2);
        $this->assertEquals('kg', $log2->liftSets->first()->unit);
        $this->assertEquals(100.0, $log2->liftSets->first()->weight);

        $pr = PersonalRecord::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->where('lift_log_id', $log2->id)
            ->where('pr_type', 'rep_specific')
            ->first();

        $this->assertNotNull($pr);
        $this->assertEquals('kg', $pr->unit);
        $this->assertEquals(100.0, $pr->value);

        // 7. User views exercise page -> assert heaviest lifts are in kg
        $prService = app(ExercisePRService::class);
        $prData = $prService->getPRData($this->exercise, $this->user);
        
        $this->assertNotNull($prData);
        $this->assertArrayHasKey('rep_5', $prData);
        $this->assertEquals(100.0, $prData['rep_5']['weight']);

        // 8. User switches preference back to 'lbs'
        $response = $this->patch(route('profile.update-preferences'), [
            'weight_unit' => 'lbs',
            'prefill_suggested_values' => true,
        ]);
        $this->user->refresh();
        $this->assertEquals('lbs', $this->user->weight_unit);
        $this->actingAs($this->user);

        $log1 = $log1->fresh(['user', 'liftSets']);
        $log2 = $log2->fresh(['user', 'liftSets']);

        // 9. User views the kg lift log -> assert display shows 220 lbs (converted back)
        $formattedDisplay2 = $strategy->formatWeightDisplay($log2);
        $this->assertEquals('220 lbs', $formattedDisplay2);

        // 10. User views original lbs lift log -> assert display shows 200 lbs (unchanged)
        $formattedDisplay1 = $strategy->formatWeightDisplay($log1);
        $this->assertEquals('200 lbs', $formattedDisplay1);

        // 11. Assert progression suggestion is in lbs with 5 lb increment
        // Last session was 100 kg (220 lbs). For a lbs user, progression should suggest 225 lbs.
        $lastSessionData = [
            'weight' => 100.0,
            'unit' => 'kg',
            'reps' => 5,
            'sets' => 3,
            'date' => $log2->logged_at->format('M j'),
        ];
        
        $progressionService = app(LiftProgressionService::class);
        $defaults = $progressionService->prepareCreateDefaults($this->exercise, $lastSessionData, $this->user->id, $this->user);
        
        $this->assertEquals(225.0, $defaults['weight']);
    }
}
