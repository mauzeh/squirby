<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class CreateExerciseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function user_can_create_new_exercise_and_is_redirected_to_lift_logs_create()
    {
        $exerciseName = 'New Custom Exercise';
        $date = Carbon::today()->toDateString();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => $exerciseName,
            'date' => $date
        ]);

        // Should create the exercise
        $this->assertDatabaseHas('exercises', [
            'title' => $exerciseName,
            'user_id' => $this->user->id,
            'exercise_type' => 'regular'
        ]);

        // Should redirect to lift-logs/create (without date param for today)
        $exercise = Exercise::where('title', $exerciseName)->first();
        $response->assertRedirect(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'redirect_to' => 'mobile-entry-lifts'
        ]));

        // Should have success message
        $response->assertSessionHas('success');
        $this->assertStringContainsString($exerciseName, session('success'));
    }

    #[Test]
    public function creating_exercise_with_existing_name_redirects_to_existing_exercise()
    {
        $existingExercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Bench Press'
        ]);

        $date = Carbon::today()->toDateString();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Bench Press',
            'date' => $date
        ]);

        // Should NOT create a duplicate
        $this->assertEquals(1, Exercise::where('title', 'Bench Press')->count());

        // Should redirect to lift-logs/create for existing exercise (without date param for today)
        $response->assertRedirect(route('lift-logs.create', [
            'exercise_id' => $existingExercise->id,
            'redirect_to' => 'mobile-entry-lifts'
        ]));
    }

    #[Test]
    public function create_exercise_requires_exercise_name()
    {
        $response = $this->post(route('mobile-entry.create-exercise'), [
            'date' => Carbon::today()->toDateString()
        ]);

        $response->assertSessionHasErrors('exercise_name');
    }

    #[Test]
    public function create_exercise_generates_canonical_name()
    {
        // Create exercise
        $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'My Custom Exercise',
            'date' => Carbon::today()->toDateString()
        ]);

        // Should have a canonical name
        $exercise = Exercise::where('title', 'My Custom Exercise')->first();
        $this->assertNotNull($exercise->canonical_name);
        $this->assertEquals('my_custom_exercise', $exercise->canonical_name);
    }

    #[Test]
    public function create_exercise_handles_duplicate_canonical_names_for_same_user()
    {
        // Create first exercise
        Exercise::create([
            'user_id' => $this->user->id,
            'title' => 'Test Exercise',
            'canonical_name' => 'test_exercise',
            'exercise_type' => 'regular'
        ]);

        // Try to create another with same name
        $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Test Exercise',
            'date' => Carbon::today()->toDateString()
        ]);

        // Should redirect to the existing exercise instead of creating duplicate
        $exercises = Exercise::where('user_id', $this->user->id)
            ->where('title', 'Test Exercise')
            ->get();
        $this->assertCount(1, $exercises);
    }

    #[Test]
    public function create_exercise_uses_default_date_if_not_provided()
    {
        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Test Exercise'
        ]);

        $exercise = Exercise::where('title', 'Test Exercise')->first();
        
        // Should redirect without date param since it defaults to today
        $response->assertRedirect(route('lift-logs.create', [
            'exercise_id' => $exercise->id,
            'redirect_to' => 'mobile-entry-lifts'
        ]));
    }

    #[Test]
    public function create_exercise_includes_date_param_for_historical_dates()
    {
        $historicalDate = Carbon::yesterday()->toDateString();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Historical Exercise',
            'date' => $historicalDate
        ]);

        $exercise = Exercise::where('title', 'Historical Exercise')->first();
        
        // Should redirect WITH date param for historical dates
        $response->assertRedirect();
        $redirectUrl = $response->headers->get('Location');
        
        // Check that the URL contains all expected parameters
        $this->assertStringContainsString('exercise_id=' . $exercise->id, $redirectUrl);
        $this->assertStringContainsString('date=' . $historicalDate, $redirectUrl);
        $this->assertStringContainsString('redirect_to=mobile-entry-lifts', $redirectUrl);
        $this->assertStringContainsString('/lift-logs/create', $redirectUrl);
    }
}
