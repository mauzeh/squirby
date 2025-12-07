<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class LiftLogLoggingTest extends TestCase {

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function a_user_can_view_the_lift_log_logging_page()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);
        $response = $this->get(route('exercises.show-logs', ['exercise' => $exercise->id]));

        $response->assertStatus(200);
    }

    /** @test */
    public function a_user_can_create_a_lift_log()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        $now = now();
        $testTime = '14:30'; // Use a time that's already on 15-minute interval

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Test lift log comments',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $testTime,
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Test lift log comments',
            'logged_at' => \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $testTime)->format('Y-m-d H:i:s'),
        ]);

        $liftLog = \App\Models\LiftLog::where('exercise_id', $exercise->id)->first();

        $this->assertDatabaseCount('lift_sets', 3);
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 100,
            'reps' => 5,
            'notes' => 'Test lift log comments',
        ]);

        $response->assertRedirect(route('exercises.show-logs', ['exercise' => $exercise->id]));
        
        // Check that we have a celebratory success message containing the exercise name and workout details
        $successMessage = session('success');
        $this->assertStringContainsString($exercise->title, $successMessage);
        $this->assertStringContainsString('100 lbs', $successMessage);
        $this->assertStringContainsString('5 reps', $successMessage);
    }

    /** @test */
    public function a_user_can_update_a_lift_log()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Original comments',
        ]);
        $liftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => 'Original comments',
        ]);

        // dd($liftLog->toArray()); // Inspect initial lift log data

        $updatedExercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);
        $updatedLiftLogData = [
            'exercise_id' => $updatedExercise->id,
            'weight' => 120,
            'reps' => 6,
            'rounds' => 4,
            'comments' => 'Updated comments',
            'date' => $liftLog->logged_at->format('Y-m-d'),
            'logged_at' => $liftLog->logged_at->format('H:i'),
        ];

        $response = $this->put(route('lift-logs.update', $liftLog->id), $updatedLiftLogData);

        // dd(\App\Models\LiftLog::find($liftLog->id)->toArray()); // Inspect lift log data after update

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'user_id' => $this->user->id,
            'exercise_id' => $updatedExercise->id,
            'comments' => 'Updated comments',
        ]);

        $this->assertCount(4, $liftLog->liftSets()->get());
        $this->assertCount(5, $liftLog->liftSets()->withTrashed()->get());

        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 120,
            'reps' => 6,
            'notes' => 'Updated comments',
        ]);

        $response->assertRedirect(route('exercises.show-logs', ['exercise' => $updatedExercise->id]))->assertSessionHas('success', 'Lift log updated successfully.');
    }

    /** @test */
    public function a_user_can_view_exercise_logs_page(): void
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        $response = $this->get('/exercises/' . $exercise->id . '/logs');

        $response->assertStatus(200);
        $response->assertSee($exercise->name);
    }

    /** @test */
    public function a_user_can_view_lift_logs_on_exercise_logs_page()
    {
        $backSquat = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id, 'title' => 'Back Squat']);

        $liftLog1 = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $backSquat->id,
            'comments' => 'Squat lift log 1 comments',
        ]);
        $liftLog1->liftSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat lift log 1 comments',
        ]);
        $liftLog1->liftSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat lift log 1 comments',
        ]);
        $liftLog1->liftSets()->create([
            'weight' => 200,
            'reps' => 5,
            'notes' => 'Squat lift log 1 comments',
        ]);

        $liftLog2 = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $backSquat->id,
            'comments' => 'Deadlift comments',
        ]);
        $liftLog2->liftSets()->create([
            'weight' => 300,
            'reps' => 3,
            'notes' => 'Deadlift comments',
        ]);

        $response = $this->get('/exercises/' . $backSquat->id . '/logs');
        $response->assertStatus(200);

        // Assert Back Squat lift log details
        $response->assertSee($backSquat->title);
        $response->assertSee($liftLog1->display_weight . ' lbs');
        $response->assertSee($liftLog1->display_rounds . ' x ' . $liftLog1->display_reps);
        $response->assertSee($liftLog1->comments);
        $response->assertSee($liftLog2->display_weight . ' lbs');
        $response->assertSee($liftLog2->display_rounds . ' x ' . $liftLog2->display_reps);
        $response->assertSee($liftLog2->comments);
    }

    /** @test */
    public function a_user_can_create_a_bodyweight_lift_log()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id, 'exercise_type' => 'bodyweight']);

        $now = now();
        $testTime = '09:15'; // Use a time that's already on 15-minute interval

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 0, // Bodyweight exercise, weight should be 0
            'reps' => 8,
            'rounds' => 3,
            'comments' => 'Bodyweight lift log comments',
            'date' => $now->format('Y-m-d'),
            'logged_at' => $testTime,
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        $this->assertDatabaseHas('lift_logs', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Bodyweight lift log comments',
            'logged_at' => \Carbon\Carbon::parse($now->format('Y-m-d') . ' ' . $testTime)->format('Y-m-d H:i:s'),
        ]);

        $liftLog = \App\Models\LiftLog::where('exercise_id', $exercise->id)->first();

        $this->assertDatabaseCount('lift_sets', 3);
        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 8,
            'notes' => 'Bodyweight lift log comments',
        ]);

        $response->assertRedirect(route('exercises.show-logs', ['exercise' => $exercise->id]));
        
        // Check that we have a celebratory success message containing the exercise name and workout details
        $successMessage = session('success');
        $this->assertStringContainsString($exercise->title, $successMessage);
        $this->assertStringContainsString('8 reps', $successMessage);
    }

    /** @test */
    public function a_user_can_update_a_bodyweight_lift_log()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id, 'exercise_type' => 'bodyweight']);
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'comments' => 'Original bodyweight comments',
        ]);
        $liftLog->liftSets()->create([
            'weight' => 0,
            'reps' => 5,
            'notes' => 'Original bodyweight comments',
        ]);

        $updatedExercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id, 'exercise_type' => 'bodyweight']);
        $updatedLiftLogData = [
            'exercise_id' => $updatedExercise->id,
            'weight' => 0,
            'reps' => 10,
            'rounds' => 4,
            'comments' => 'Updated bodyweight comments',
            'date' => $liftLog->logged_at->format('Y-m-d'),
            'logged_at' => $liftLog->logged_at->format('H:i'),
        ];

        $response = $this->put(route('lift-logs.update', $liftLog->id), $updatedLiftLogData);

        $this->assertDatabaseHas('lift_logs', [
            'id' => $liftLog->id,
            'user_id' => $this->user->id,
            'exercise_id' => $updatedExercise->id,
            'comments' => 'Updated bodyweight comments',
        ]);

        $this->assertCount(4, $liftLog->liftSets()->get());
        $this->assertCount(5, $liftLog->liftSets()->withTrashed()->get());

        $this->assertDatabaseHas('lift_sets', [
            'lift_log_id' => $liftLog->id,
            'weight' => 0,
            'reps' => 10,
            'notes' => 'Updated bodyweight comments',
        ]);

        $response->assertRedirect(route('exercises.show-logs', ['exercise' => $updatedExercise->id]))->assertSessionHas('success', 'Lift log updated successfully.');
    }

    /** @test */
    public function a_user_can_view_bodyweight_exercise_logs_with_correct_weight_display()
    {
        $exercise = \App\Models\Exercise::factory()->create([
            'user_id' => $this->user->id, 
            'title' => 'Chin-Ups', 
            'exercise_type' => 'bodyweight'
        ]);
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);
        $liftLog->liftSets()->create([
            'weight' => 0,
            'reps' => 5,
            'notes' => 'Bodyweight set',
        ]);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // The flexible view uses badges for display, so check for the exercise title and reps
        $response->assertSee($exercise->title);
        $response->assertSee('1 x 5'); // Badge format: rounds x reps
    }

    /** @test */
    public function a_user_can_view_bodyweight_exercise_logs_with_correct_1rm_display()
    {
        $exercise = \App\Models\Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Chin-Ups',
            'exercise_type' => 'bodyweight'
        ]);
        $liftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);
        $liftLog->liftSets()->create([
            'weight' => 0,
            'reps' => 5,
            'notes' => 'Bodyweight set',
        ]);

        $response = $this->get(route('exercises.show-logs', $exercise));

        $response->assertStatus(200);
        // Bodyweight exercises should not show 1RM calculations
        $response->assertDontSee('lbs (est. incl. BW)');
    }

    /** @test */
    public function first_lift_log_is_marked_as_pr()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'First lift',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // First lift should be marked as PR
        $response->assertSessionHas('is_pr', true);
        
        // Success message should contain PR indicator
        $successMessage = session('success');
        $this->assertStringContainsString('NEW PR!', $successMessage);
    }

    /** @test */
    public function heavier_lift_is_marked_as_pr()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        // Log first lift at 100 lbs
        $firstLiftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLiftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => 'First lift',
        ]);

        // Log second lift at 120 lbs (heavier = PR)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 120,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Heavier lift',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Heavier lift should be marked as PR
        $response->assertSessionHas('is_pr', true);
        
        // Success message should contain PR indicator
        $successMessage = session('success');
        $this->assertStringContainsString('NEW PR!', $successMessage);
    }

    /** @test */
    public function lighter_lift_is_not_marked_as_pr()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        // Log first lift at 120 lbs
        $firstLiftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLiftLog->liftSets()->create([
            'weight' => 120,
            'reps' => 5,
            'notes' => 'Heavy lift',
        ]);

        // Log second lift at 100 lbs (lighter = not a PR)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Lighter lift',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Lighter lift should NOT be marked as PR
        $response->assertSessionHas('is_pr', false);
        
        // Success message should NOT contain PR indicator
        $successMessage = session('success');
        $this->assertStringNotContainsString('NEW PR!', $successMessage);
    }

    /** @test */
    public function equal_weight_lift_is_not_marked_as_pr()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        // Log first lift at 100 lbs
        $firstLiftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLiftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => 'First lift',
        ]);

        // Log second lift at same weight (equal = not a PR)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 100,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Same weight',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Equal weight should NOT be marked as PR
        $response->assertSessionHas('is_pr', false);
        
        // Success message should NOT contain PR indicator
        $successMessage = session('success');
        $this->assertStringNotContainsString('NEW PR!', $successMessage);
    }

    /** @test */
    public function bodyweight_exercises_are_not_marked_as_pr()
    {
        $exercise = \App\Models\Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'bodyweight'
        ]);

        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 0,
            'reps' => 10,
            'rounds' => 3,
            'comments' => 'Bodyweight exercise',
            'date' => now()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Bodyweight exercises should NOT be marked as PR
        $response->assertSessionHas('is_pr', false);
        
        // Success message should NOT contain PR indicator
        $successMessage = session('success');
        $this->assertStringNotContainsString('NEW PR!', $successMessage);
    }

    /** @test */
    public function pr_detection_only_considers_previous_lifts()
    {
        $exercise = \App\Models\Exercise::factory()->create(['user_id' => $this->user->id]);

        // Log first lift at 100 lbs yesterday
        $firstLiftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now()->subDay(),
        ]);
        $firstLiftLog->liftSets()->create([
            'weight' => 100,
            'reps' => 5,
            'notes' => 'First lift',
        ]);

        // Log second lift at 120 lbs today (PR)
        $secondLiftLog = \App\Models\LiftLog::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);
        $secondLiftLog->liftSets()->create([
            'weight' => 120,
            'reps' => 5,
            'notes' => 'PR lift',
        ]);

        // Log third lift at 110 lbs tomorrow (not a PR, even though it's heavier than first)
        $liftLogData = [
            'exercise_id' => $exercise->id,
            'weight' => 110,
            'reps' => 5,
            'rounds' => 3,
            'comments' => 'Not a PR',
            'date' => now()->addDay()->format('Y-m-d'),
            'logged_at' => '14:30',
        ];

        $response = $this->post(route('lift-logs.store'), $liftLogData);

        // Should NOT be marked as PR because 120 lbs was already logged
        $response->assertSessionHas('is_pr', false);
    }


}