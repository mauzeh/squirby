<?php

namespace Tests\Unit\Mail;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\LiftLog;
use App\Mail\FirstLiftOfTheDay;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase; // Add this

class FirstLiftOfTheDayTest extends TestCase
{
    use RefreshDatabase; // Add this
    /**
     * Test that the mailable's subject is correctly personalized.
     *
     * @return void
     */
    public function test_mailable_subject_is_personalized()
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $liftLog = LiftLog::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'logged_at' => now(),
        ]);

        $environmentFile = '.env.testing'; // Mock environment file for consistency

        $mailable = new FirstLiftOfTheDay($liftLog, $environmentFile);

        $this->assertEquals('Hello Test User, your First Lift Of The Day!', $mailable->envelope()->subject);
    }
}
