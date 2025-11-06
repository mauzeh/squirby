<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exercise;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileEntryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_access_the_mobile_entry_lifts_page()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.index');
    }

    /** @test */
    public function mobile_entry_page_loads_with_programs()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // Create a program for today
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => now(),
            'sets' => 3,
            'reps' => 5
        ]);
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertSee($exercise->title);
    }

    /** @test */
    public function mobile_entry_page_loads_with_date_parameter()
    {
        $user = User::factory()->create();
        $testDate = '2024-01-15';
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts', ['date' => $testDate]));
        
        $response->assertStatus(200);
        $response->assertViewIs('mobile-entry.index');
    }

    /** @test */
    public function mobile_entry_page_requires_authentication()
    {
        $response = $this->get(route('mobile-entry.lifts'));
        
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function mobile_entry_page_renders_forms_without_errors()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Test Exercise',
            'exercise_type' => 'regular'
        ]);
        
        // Create a program that will generate a form
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => now(),
            'sets' => 3,
            'reps' => 5
        ]);
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        // Check that form elements are present
        $response->assertSee('Log Test Exercise');
        $response->assertSee('Reps:');
        $response->assertSee('Sets:');
    }

    /** @test */
    public function mobile_entry_page_renders_bodyweight_exercise_forms()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Push-ups',
            'exercise_type' => 'bodyweight'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => now(),
            'sets' => 3,
            'reps' => 10
        ]);
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertSee('Push-ups');
        $response->assertSee('Log Push-ups');
    }

    /** @test */
    public function mobile_entry_page_renders_band_exercise_forms()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'title' => 'Band Pull-aparts',
            'exercise_type' => 'banded_resistance'
        ]);
        
        Program::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'date' => now(),
            'sets' => 3,
            'reps' => 15
        ]);
        
        $response = $this->actingAs($user)->get(route('mobile-entry.lifts'));
        
        $response->assertStatus(200);
        $response->assertSee('Band Pull-aparts');
        $response->assertSee('Band Color:');
    }
}