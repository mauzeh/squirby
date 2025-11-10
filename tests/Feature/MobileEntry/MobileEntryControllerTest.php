<?php

namespace Tests\Feature\MobileEntry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\MobileLiftForm;
use Carbon\Carbon;

class MobileEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function lifts_page_loads_with_mobile_lift_forms()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null
        ]);

        $today = Carbon::today();

        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $today
        ]);

        $response = $this->get(route('mobile-entry.lifts', ['date' => $today->toDateString()]));

        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.index');
        
        $data = $response->viewData('data');
        $this->assertCount(1, $data['forms']);
        $this->assertEquals('Bench Press', $data['forms'][0]['title']);
    }

    /** @test */
    public function addLiftForm_creates_mobile_lift_forms_record()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Squats',
            'user_id' => null
        ]);

        $today = Carbon::today();

        $response = $this->get(route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $today->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mobile_lift_forms', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id
        ]);
    }

    /** @test */
    public function addLiftForm_returns_error_for_duplicate_form()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Deadlift',
            'user_id' => null
        ]);

        $today = Carbon::today();

        // Create the form first
        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $today
        ]);

        // Try to add it again
        $response = $this->get(route('mobile-entry.add-lift-form', [
            'exercise' => $exercise->id,
            'date' => $today->toDateString()
        ]));

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('error');

        // Should still only have one record
        $this->assertEquals(1, MobileLiftForm::where('user_id', $this->user->id)
            ->where('exercise_id', $exercise->id)
            ->where('date', $today)
            ->count());
    }

    /** @test */
    public function removeForm_deletes_mobile_lift_forms_record()
    {
        $exercise = Exercise::factory()->create([
            'title' => 'Pull-ups',
            'user_id' => null
        ]);

        $today = Carbon::today();

        $form = MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $today
        ]);

        $response = $this->delete(route('mobile-entry.remove-form', ['id' => 'lift-' . $form->id]), [
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('mobile_lift_forms', [
            'id' => $form->id
        ]);
    }

    /** @test */
    public function removeForm_returns_error_for_nonexistent_form()
    {
        $today = Carbon::today();

        $response = $this->delete(route('mobile-entry.remove-form', ['id' => 'lift-99999']), [
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function createExercise_creates_exercise_and_mobile_lift_forms_record()
    {
        $today = Carbon::today();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Custom Exercise',
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('exercises', [
            'title' => 'Custom Exercise',
            'user_id' => $this->user->id
        ]);

        $exercise = Exercise::where('title', 'Custom Exercise')
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertDatabaseHas('mobile_lift_forms', [
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id
        ]);
    }

    /** @test */
    public function createExercise_returns_error_for_duplicate_exercise()
    {
        Exercise::factory()->create([
            'title' => 'Existing Exercise',
            'user_id' => $this->user->id
        ]);

        $today = Carbon::today();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'exercise_name' => 'Existing Exercise',
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('error');

        // Should still only have one exercise with this name
        $this->assertEquals(1, Exercise::where('title', 'Existing Exercise')
            ->where('user_id', $this->user->id)
            ->count());
    }

    /** @test */
    public function createExercise_validates_required_fields()
    {
        $today = Carbon::today();

        $response = $this->post(route('mobile-entry.create-exercise'), [
            'date' => $today->toDateString()
            // Missing exercise_name
        ]);

        $response->assertSessionHasErrors('exercise_name');
    }

    /** @test */
    public function user_cannot_remove_another_users_form()
    {
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null
        ]);

        $today = Carbon::today();

        $form = MobileLiftForm::factory()->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $exercise->id,
            'date' => $today
        ]);

        $response = $this->delete(route('mobile-entry.remove-form', ['id' => 'lift-' . $form->id]), [
            'date' => $today->toDateString()
        ]);

        $response->assertRedirect(route('mobile-entry.lifts', ['date' => $today->toDateString()]));
        $response->assertSessionHas('error');

        // Form should still exist
        $this->assertDatabaseHas('mobile_lift_forms', [
            'id' => $form->id
        ]);
    }

    /** @test */
    public function lifts_page_shows_multiple_forms_for_same_date()
    {
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);
        $exercise2 = Exercise::factory()->create(['title' => 'Squats', 'user_id' => null]);
        $exercise3 = Exercise::factory()->create(['title' => 'Deadlift', 'user_id' => null]);

        $today = Carbon::today();

        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'date' => $today
        ]);

        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'date' => $today
        ]);

        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise3->id,
            'date' => $today
        ]);

        $response = $this->get(route('mobile-entry.lifts', ['date' => $today->toDateString()]));

        $response->assertStatus(200);
        
        $data = $response->viewData('data');
        $this->assertCount(3, $data['forms']);
    }

    /** @test */
    public function lifts_page_only_shows_forms_for_selected_date()
    {
        $exercise = Exercise::factory()->create(['title' => 'Bench Press', 'user_id' => null]);

        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        // Create form for today
        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $today
        ]);

        // Create form for yesterday
        MobileLiftForm::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'date' => $yesterday
        ]);

        $response = $this->get(route('mobile-entry.lifts', ['date' => $today->toDateString()]));

        $response->assertStatus(200);
        
        $data = $response->viewData('data');
        $this->assertCount(1, $data['forms']);
    }
}
