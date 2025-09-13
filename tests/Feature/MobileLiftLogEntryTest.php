<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Carbon\Carbon;

class MobileLiftLogEntryTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = []; // Disable transactions for RefreshDatabase

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate'); // Ensure migrations are run
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function authenticated_user_can_access_mobile_lift_log_entry_page()
    {
        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSeeText("No program entries for this day.");
    }

    /** @test */
    public function mobile_entry_page_displays_programs_for_selected_date()
    {
        $exercise1 = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $exercise2 = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => true]);

        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'date' => Carbon::today(),
            'sets' => 4,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSee($exercise1->title);
        $response->assertSee('Target: 3 sets of 5 reps');
        $response->assertSee($exercise2->title);
        $response->assertSee('Target: 4 sets of 10 reps');
    }

    /** @test */
    public function mobile_entry_page_displays_suggested_weight_for_non_bodyweight_exercises_today()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        // Create a past lift log to generate a suggested weight
        LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()->subDays(1),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSee('Suggested:'); // Check for the presence of suggested weight
    }

    /** @test */
    public function mobile_entry_page_does_not_display_suggested_weight_for_bodyweight_exercises()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => true]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertDontSee('Suggested:');
    }

    /** @test */
    public function mobile_entry_page_does_not_display_suggested_weight_for_past_dates()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->subDay(), // Past date
            'sets' => 3,
            'reps' => 5,
        ]);

        // Create a past lift log to generate a suggested weight
        LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()->subDays(2),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => Carbon::today()->subDay()->toDateString()]));

        $response->assertStatus(200);
        $response->assertDontSee('Suggested:');
    }

    /** @test */
    public function mobile_entry_page_does_not_display_suggested_weight_for_future_dates_beyond_tomorrow()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today()->addDays(3), // Day after tomorrow
            'sets' => 3,
            'reps' => 5,
        ]);

        // Create a past lift log to generate a suggested weight
        LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()->subDays(1),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry', ['date' => Carbon::today()->addDays(3)->toDateString()]));

        $response->assertStatus(200);
        $response->assertDontSee('Suggested:');
    }

    /** @test */
    public function authenticated_user_can_submit_lift_log_from_mobile_entry_page()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        $now = now();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Mobile entry test log',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $now->format('H:i'),
            'redirect_to' => 'mobile-entry',
            'program_id' => $program->id,
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $response->assertRedirect(route('lift-logs.mobile-entry', [
            'date' => $now->format('Y-m-d'),
            'submitted_lift_log_id' => LiftLog::latest()->first()->id,
            'submitted_program_id' => $program->id,
        ]));

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Mobile entry test log',
        ]);
    }

    /** @test */
    public function mobile_entry_page_shows_logged_summary_and_hides_form_if_lift_exists()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        // Log a lift for today
        $liftLog = LiftLog::factory()->has(LiftSet::factory()->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today(),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSee('Completed!');
        $response->assertSee($liftLog->display_weight . ' lbs');
        $response->assertDontSee('Add Log'); // Form button should not be visible
    }

    /** @test */
    public function mobile_entry_page_shows_logged_summary_after_submission()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        $now = now();

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Mobile entry test log',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $now->format('H:i'),
            'redirect_to' => 'mobile-entry',
            'program_id' => $program->id,
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Follow the redirect to the mobile entry page
        $response = $this->get($response->headers->get('Location'));

        $response->assertStatus(200);
        $response->assertSee('Completed!');
        $response->assertSee('Mobile entry test log');
        $response->assertDontSee('Add Log'); // Form button should not be visible
    }

    /** @test */
    public function mobile_entry_page_navigates_to_previous_and_next_days()
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();
        $tomorrow = $today->copy()->addDay();

        // Test navigation to previous day
        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $today->toDateString()]));
        $response->assertSee(route('lift-logs.mobile-entry', ['date' => $yesterday->toDateString()]));

        // Test navigation to next day
        $response = $this->get(route('lift-logs.mobile-entry', ['date' => $today->toDateString()]));
        $response->assertSee(route('lift-logs.mobile-entry', ['date' => $tomorrow->toDateString()]));
    }

    
}
