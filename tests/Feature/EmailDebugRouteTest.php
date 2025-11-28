<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Support\Facades\App;
class EmailDebugRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the debug email route renders correctly.
     *
     * @return void
     */
    public function test_debug_email_route_renders_correctly()
    {
        // Create a user
        $user = User::factory()->create();

        // Create an exercise
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // Create a lift log with lift sets
        $liftLog = LiftLog::factory()
            ->has(LiftSet::factory()->count(3), 'liftSets')
            ->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => now(),
            ]);

        $response = $this->get('/debug/email');

        $response->assertStatus(200);
        $response->assertSee('Hello ' . $user->name);
        $response->assertSeeText($exercise->getDisplayNameForUser($user));

        $firstSet = $liftLog->liftSets->first();
        $weight = $firstSet->weight ?? 0;
        $reps = $firstSet->reps ?? 0;
        $bandColor = $firstSet->band_color ?? null;
        $rounds = $liftLog->liftSets->count();
        $strategy = $liftLog->exercise->getTypeStrategy();
        $workoutDescription = $strategy->formatSuccessMessageDescription($weight, $reps, $rounds, $bandColor);

        $response->assertSeeText($workoutDescription);
        $response->assertSeeText('View Your Lift');
        $response->assertSee(route('exercises.show-logs', $liftLog->exercise), false); // Use false for not escaping HTML
        $response->assertSeeText('Environment file: ' . app()->environmentFile());
    }

    /**
     * Test that the debug email route returns a message if no lift logs exist.
     *
     * @return void
     */
    public function test_debug_email_route_no_lift_logs_message()
    {
        $response = $this->get('/debug/email');

        $response->assertStatus(200);
        $response->assertSeeText('No lift logs found to render the email.');
    }
}
