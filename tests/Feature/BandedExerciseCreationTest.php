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
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Banded Pull-ups',
            'user_id' => $this->user->id,
            'is_bodyweight' => false,
            'band_type' => 'resistance',
        ]);
    }

    /** @test */
    public function a_user_can_create_an_assistance_band_exercise()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Assisted Push-ups',
            'description' => 'Push-ups with an assistance band',
            'is_bodyweight' => true,
            'band_type' => 'assistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Assisted Push-ups',
            'user_id' => $this->user->id,
            'is_bodyweight' => false, // Should be false if band_type is set
            'band_type' => 'assistance',
        ]);
    }

    /** @test */
    public function creating_a_banded_exercise_sets_is_bodyweight_to_false()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Banded Dips',
            'description' => 'Dips with a resistance band',
            'is_bodyweight' => true, // User tries to set it to true
            'band_type' => 'resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Banded Dips',
            'user_id' => $this->user->id,
            'is_bodyweight' => false, // Should be overridden to false
            'band_type' => 'resistance',
        ]);
    }

    /** @test */
    public function a_user_can_edit_a_banded_exercise()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
            'title' => 'Old Banded Exercise',
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => 'Updated Banded Exercise',
            'description' => 'Updated description',
            'is_bodyweight' => false,
            'band_type' => 'assistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'title' => 'Updated Banded Exercise',
            'band_type' => 'assistance',
        ]);
    }

    /** @test */
    public function editing_a_banded_exercise_overrides_is_bodyweight()
    {
        $this->actingAs($this->user);
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
            'band_type' => 'resistance',
            'is_bodyweight' => true,
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => $exercise->title,
            'description' => $exercise->description,
            'is_bodyweight' => true, // User tries to set it to true
            'band_type' => 'resistance',
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'is_bodyweight' => false, // Should be overridden to false
            'band_type' => 'resistance',
        ]);
    }

    /** @test */
    public function an_admin_can_create_a_global_banded_exercise()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Global Banded Exercise',
            'description' => 'A global banded exercise',
            'is_bodyweight' => false,
            'band_type' => 'resistance',
            'is_global' => true,
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'Global Banded Exercise',
            'user_id' => null,
            'band_type' => 'resistance',
        ]);
    }

    /** @test */
    public function an_admin_can_edit_a_global_banded_exercise()
    {
        $this->actingAs($this->admin);
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'band_type' => 'resistance',
            'title' => 'Old Global Banded Exercise',
        ]);

        $response = $this->put(route('exercises.update', $exercise->id), [
            'title' => 'Updated Global Banded Exercise',
            'description' => 'Updated global description',
            'is_bodyweight' => false,
            'band_type' => 'assistance',
            'is_global' => true,
        ]);

        $response->assertRedirect(route('exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'id' => $exercise->id,
            'title' => 'Updated Global Banded Exercise',
            'band_type' => 'assistance',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function band_type_validation_works()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('exercises.store'), [
            'title' => 'Invalid Band Exercise',
            'band_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors('band_type');
        $this->assertDatabaseMissing('exercises', [
            'title' => 'Invalid Band Exercise',
        ]);
    }
}
