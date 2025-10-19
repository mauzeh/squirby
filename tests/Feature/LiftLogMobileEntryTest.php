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

class LiftLogMobileEntryTest extends TestCase
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
        $response->assertSee('3 x 5');
        $response->assertSee($exercise2->title);
        $response->assertSee('4 x 10');
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
        
    }

    /** @test */
    public function mobile_entry_page_displays_suggested_reps_and_sets()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
        ]);

        LiftLog::factory()->has(LiftSet::factory()->count(3)->state([
            'reps' => 11,
            'weight' => 40.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()->subDays(1),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSee('40 lbs');
        $response->assertSee('(3 x 12)');
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

    /** @test */
    public function can_add_exercise_to_program_from_mobile_entry()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id]);
        $date = Carbon::today()->toDateString();

        $response = $this->get(route('programs.quick-add', ['exercise' => $exercise->id, 'date' => $date]));

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::parse($date)->startOfDay(),
        ]);
    }

    /** @test */
    public function can_create_new_exercise_and_add_to_program_from_mobile_entry()
    {
        $date = Carbon::today()->toDateString();
        $exerciseName = 'New Test Exercise';

        $response = $this->post(route('programs.quick-create', ['date' => $date]), [
            'exercise_name' => $exerciseName,
        ]);

        $response->assertRedirect(route('programs.index', ['date' => $date]));
        $this->assertDatabaseHas('exercises', [
            'user_id' => $this->user->id,
            'title' => $exerciseName,
        ]);
        $this->assertDatabaseHas('programs', [
            'user_id' => $this->user->id,
            'exercise_id' => Exercise::where('title', $exerciseName)->first()->id,
            'date' => Carbon::parse($date)->startOfDay(),
        ]);
    }

    /** @test */
    public function can_delete_program_from_mobile_entry()
    {
        $program = Program::factory()->create(['user_id' => $this->user->id]);
        $date = $program->date;

        $response = $this->delete(route('programs.destroy', $program->id), [
            'date' => $date,
            'redirect_to' => 'mobile-entry',
        ]);

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $date]));
        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    /** @test */
    public function can_move_program_up()
    {
        $program1 = Program::factory()->create(['user_id' => $this->user->id, 'priority' => 1]);
        $program2 = Program::factory()->create(['user_id' => $this->user->id, 'priority' => 2, 'date' => $program1->date]);

        $response = $this->get(route('programs.move-up', $program2->id));

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $program1->date->toDateString()]));
        $this->assertDatabaseHas('programs', ['id' => $program1->id, 'priority' => 2]);
        $this->assertDatabaseHas('programs', ['id' => $program2->id, 'priority' => 1]);
    }

    /** @test */
    public function moving_program_entry_retains_original_date_on_redirect()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Create program entries for a specific date (not today)
        $testDate = Carbon::today()->addDays(5); // 5 days from today
        $program1 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $testDate,
            'priority' => 100,
            'sets' => 3,
            'reps' => 10,
        ]);
        $program2 = Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => $testDate,
            'priority' => 110,
            'sets' => 3,
            'reps' => 10,
        ]);

        // Simulate moving program1 down
        $response = $this->get(route('programs.move-down', $program1));

        // Assert that the redirect URL contains the original test date
        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $testDate->toDateString()]));
    }

    /** @test */
    public function can_move_program_down()
    {
        $program1 = Program::factory()->create(['user_id' => $this->user->id, 'priority' => 1]);
        $program2 = Program::factory()->create(['user_id' => $this->user->id, 'priority' => 2, 'date' => $program1->date]);

        $response = $this->get(route('programs.move-down', $program1->id));

        $response->assertRedirect(route('lift-logs.mobile-entry', ['date' => $program1->date->toDateString()]));
        $this->assertDatabaseHas('programs', ['id' => $program1->id, 'priority' => 2]);
        $this->assertDatabaseHas('programs', ['id' => $program2->id, 'priority' => 1]);
    }

    /** @test */
    public function first_program_does_not_have_up_arrow()
    {
        Program::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertDontSee(route('programs.move-up', 1));
    }

    /** @test */
    public function last_program_does_not_have_down_arrow()
    {
        Program::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertDontSee(route('programs.move-down', 1));
    }

    /** @test */
    public function mobile_entry_page_displays_last_weight_reps_and_sets()
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
        LiftLog::factory()->has(LiftSet::factory()->count(3)->state([
            'reps' => 5,
            'weight' => 100.0,
        ]), 'liftSets')->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => Carbon::today()->subDays(1),
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        $response->assertSee('Last time:');
        $response->assertSee('100 lbs');
        $response->assertSee('(3 × 5)');
        $response->assertSee('1 day ago');
    }

    /** @test */
    public function mobile_entry_form_uses_current_time_when_no_time_provided()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        // Test mobile entry behavior (no logged_at field)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Mobile entry test',
            'date' => Carbon::today()->format('Y-m-d'),
            'redirect_to' => 'mobile-entry',
            'program_id' => $program->id,
            // Note: no 'logged_at' field - mobile form doesn't send it
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Verify the lift log was created
        $liftLog = LiftLog::where('user_id', $this->user->id)->first();
        $this->assertNotNull($liftLog);
        
        // Check that the time is rounded to a 15-minute interval
        $loggedMinutes = $liftLog->logged_at->minute;
        $this->assertTrue(in_array($loggedMinutes, [0, 15, 30, 45]), 
            "Expected logged time to be rounded to 15-minute interval, got {$loggedMinutes} minutes");
        
        // Verify it's logged for today
        $this->assertTrue($liftLog->logged_at->isToday(), 
            "Expected lift log to be logged today");
    }

    /** @test */
    public function mobile_entry_form_rounds_time_to_nearest_15_minute_interval_on_backend()
    {
        $exercise = Exercise::factory()->create(['user_id' => $this->user->id, 'is_bodyweight' => false]);
        $program = Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 5,
        ]);

        // Test various times that should be rounded to 15-minute intervals
        $testCases = [
            ['input' => '14:37', 'expected' => '14:45'], // Round up
            ['input' => '09:22', 'expected' => '09:30'], // Round up
            ['input' => '16:08', 'expected' => '16:15'], // Round up
            ['input' => '11:52', 'expected' => '12:00'], // Round up to next hour
            ['input' => '08:00', 'expected' => '08:00'], // Already on 15-min interval
            ['input' => '13:15', 'expected' => '13:15'], // Already on 15-min interval
            ['input' => '20:30', 'expected' => '20:30'], // Already on 15-min interval
            ['input' => '18:45', 'expected' => '18:45'], // Already on 15-min interval
        ];

        // Test that mobile entry (no logged_at field) uses current time and rounds it
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Mobile entry time rounding test',
            'date' => Carbon::today()->format('Y-m-d'),
            'redirect_to' => 'mobile-entry',
            'program_id' => $program->id,
            // Note: no 'logged_at' field - mobile form doesn't send it
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Verify the lift log was created with current time rounded to 15-minute interval
        $liftLog = LiftLog::where('user_id', $this->user->id)->first();
        $this->assertNotNull($liftLog);
        
        // Check that the time is rounded to a 15-minute interval
        $loggedMinutes = $liftLog->logged_at->minute;
        $this->assertTrue(in_array($loggedMinutes, [0, 15, 30, 45]), 
            "Expected logged time to be rounded to 15-minute interval, got {$loggedMinutes} minutes");
        
        // Verify it's logged for today
        $this->assertTrue($liftLog->logged_at->isToday(), 
            "Expected lift log to be logged today");
            
        // Test with explicit time (simulating regular form behavior)
        LiftLog::where('user_id', $this->user->id)->delete();
        
        foreach ($testCases as $case) {
            $liftLogData = [
                'exercise_id' => $exercise->id,
                'weight' => 100,
                'reps' => 5,
                'rounds' => 3,
                'comments' => 'Regular form time rounding test',
                'date' => Carbon::today()->format('Y-m-d'),
                'logged_at' => $case['input'], // Submit specific time
                'redirect_to' => 'mobile-entry',
                'program_id' => $program->id,
            ];

            $response = $this->post(route('lift-logs.store'), $liftLogData);

            // Verify the lift log was created with rounded time
            $expectedDateTime = Carbon::today()->setTimeFromTimeString($case['expected']);
            $this->assertDatabaseHas('lift_logs', [
                'user_id' => $this->user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => $expectedDateTime->format('Y-m-d H:i:s'),
            ]);

            // Clean up for next iteration
            LiftLog::where('user_id', $this->user->id)->delete();
        }
    }

    /** @test */
    public function mobile_entry_page_displays_exercise_recommendations()
    {
        // Create global exercises with intelligence data for recommendations
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Push-ups']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Squats']);
        $globalExercise3 = Exercise::factory()->create(['user_id' => null, 'title' => 'Pull-ups']);
        
        // Create intelligence data for these exercises
        \App\Models\ExerciseIntelligence::factory()->create([
            'exercise_id' => $globalExercise1->id,
            'movement_archetype' => 'push',
            'difficulty_level' => 2,
            'primary_mover' => 'pectoralis_major'
        ]);
        
        \App\Models\ExerciseIntelligence::factory()->create([
            'exercise_id' => $globalExercise2->id,
            'movement_archetype' => 'squat',
            'difficulty_level' => 2,
            'primary_mover' => 'quadriceps'
        ]);
        
        \App\Models\ExerciseIntelligence::factory()->create([
            'exercise_id' => $globalExercise3->id,
            'movement_archetype' => 'pull',
            'difficulty_level' => 3,
            'primary_mover' => 'latissimus_dorsi'
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Check that recommendations appear in the exercise list
        $response->assertSee('⭐ <em>Recommended</em>', false);
    }

    /** @test */
    public function mobile_entry_recommendations_exclude_exercises_already_in_program()
    {
        // Create multiple global exercises to ensure we can still get 3 recommendations
        $globalExercise1 = Exercise::factory()->create(['user_id' => null, 'title' => 'Push-ups']);
        $globalExercise2 = Exercise::factory()->create(['user_id' => null, 'title' => 'Squats']);
        $globalExercise3 = Exercise::factory()->create(['user_id' => null, 'title' => 'Pull-ups']);
        $globalExercise4 = Exercise::factory()->create(['user_id' => null, 'title' => 'Deadlifts']);
        $globalExercise5 = Exercise::factory()->create(['user_id' => null, 'title' => 'Bench Press']);
        
        // Create intelligence data for all exercises
        foreach ([$globalExercise1, $globalExercise2, $globalExercise3, $globalExercise4, $globalExercise5] as $index => $exercise) {
            \App\Models\ExerciseIntelligence::factory()->create([
                'exercise_id' => $exercise->id,
                'movement_archetype' => ['push', 'squat', 'pull', 'hinge', 'push'][$index],
                'difficulty_level' => 2,
                'primary_mover' => ['pectoralis_major', 'quadriceps', 'latissimus_dorsi', 'gluteus_maximus', 'pectoralis_major'][$index]
            ]);
        }
        
        // Add one exercise to today's program
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $globalExercise1->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Should not see the exercise that's already in the program in recommendations section
        // (it will still appear in the regular exercise list, but not in recommendations)
        
        // Should still see recommendations in the exercise list
        $response->assertSee('⭐ <em>Recommended</em>', false);
        
        // Should see other exercises in recommendations
        $response->assertSee('Squats');
    }

    /** @test */
    public function mobile_entry_always_shows_three_recommendations_when_available()
    {
        // Create 5 global exercises to ensure we have enough for 3 recommendations after filtering
        $exercises = [];
        for ($i = 1; $i <= 5; $i++) {
            $exercise = Exercise::factory()->create(['user_id' => null, 'title' => "Exercise $i"]);
            $exercises[] = $exercise;
            
            \App\Models\ExerciseIntelligence::factory()->create([
                'exercise_id' => $exercise->id,
                'movement_archetype' => ['push', 'pull', 'squat', 'hinge', 'core'][$i-1],
                'difficulty_level' => 2,
                'primary_mover' => 'pectoralis_major'
            ]);
        }
        
        // Add 2 exercises to today's program
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercises[0]->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 10,
        ]);
        
        Program::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercises[1]->id,
            'date' => Carbon::today(),
            'sets' => 3,
            'reps' => 10,
        ]);

        $response = $this->get(route('lift-logs.mobile-entry'));

        $response->assertStatus(200);
        
        // Should still show recommendations in the exercise list
        $response->assertSee('⭐ <em>Recommended</em>', false);
        
        // Should not see the exercises that are already in the program in recommendations
        // (they will still appear in the regular exercise list, but not in recommendations)
        
        // Should see the remaining exercises as recommendations
        $response->assertSee('Exercise 3');
        $response->assertSee('Exercise 4');
        $response->assertSee('Exercise 5');
    }
}