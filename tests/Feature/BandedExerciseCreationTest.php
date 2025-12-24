<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BandedExerciseCreationTest extends TestCase
{
    use RefreshDatabase;

    protected $withoutMiddleware = [\App\Http\Middleware\VerifyCsrfToken::class];

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        $adminRole = Role::factory()->create(['name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);
    }

    /** @test */
    public function a_user_can_create_a_resistance_band_exercise()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Banded Pull-ups',
            'description' => 'Pull-ups with a resistance band',
            'exercise_type' => 'banded_resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Banded Pull-ups',
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance',
        ]);
    }

    /** @test */
    public function a_user_can_create_an_assistance_band_exercise()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Assisted Push-ups',
            'description' => 'Push-ups with an assistance band',
            'exercise_type' => 'banded_assistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Assisted Push-ups',
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_assistance',
        ]);
    }

    /** @test */
    public function creating_a_banded_exercise_sets_exercise_type_correctly()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Banded Dips',
            'description' => 'Dips with a resistance band',
            'exercise_type' => 'banded_resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Banded Dips',
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance',
        ]);
    }

    /** @test */
    public function a_user_can_edit_a_banded_exercise()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance',
            'title' => 'Old Banded Exercise',
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => 'Updated Banded Exercise',
            'description' => 'Updated description',
            'exercise_type' => 'banded_assistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'title' => 'Updated Banded Exercise',
            'exercise_type' => 'banded_assistance',
        ]);
    }

    /** @test */
    public function editing_a_banded_exercise_maintains_exercise_type()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'exercise_type' => 'banded_resistance',
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'exercise_type' => 'banded_resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'exercise_type' => 'banded_resistance',
        ]);
    }

    /** @test */
    public function an_admin_can_create_a_global_banded_exercise()
    {
        $this->actingAs($this->admin);

        // Create user exercise first
        $response = $this->post(route('exercises.store'), [
            'title' => 'Global Banded Exercise',
            'description' => 'A global banded exercise',
            'exercise_type' => 'banded_resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Global Banded Exercise',
            'user_id' => $this->admin->id,
            'exercise_type' => 'banded_resistance',
        ]);
        
        // Promote to global
        $exercise = Exercise::where('title', 'Global Banded Exercise')->first();
        $promoteResponse = $this->post(route('exercises.promote', $exercise));
        $promoteResponse->assertRedirect(route('exercises.edit', $exercise));
        
        // Verify it's now global
        $this->assertDatabaseHas('exercises', [
            'title' => 'Global Banded Exercise',
            'user_id' => null,
            'exercise_type' => 'banded_resistance',
        ]);
    }

    /** @test */
    public function an_admin_can_edit_a_global_banded_exercise()
    {
        $this->actingAs($this->admin);
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'exercise_type' => 'banded_resistance',
            'title' => 'Old Global Banded Exercise',
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => 'Updated Global Banded Exercise',
            'description' => 'Updated global description',
            'exercise_type' => 'banded_assistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'title' => 'Updated Global Banded Exercise',
            'exercise_type' => 'banded_assistance',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function exercise_type_validation_works()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Invalid Exercise Type',
            'exercise_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('exercise_type');
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Invalid Exercise Type',
        ]);
    }
}
