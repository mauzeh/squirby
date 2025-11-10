<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\WorkoutTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkoutTemplateIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_user_templates()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create([
            'user_id' => $user->id,
            'name' => 'Push Day',
            'description' => 'Upper body push exercises'
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Workout Templates');
        $response->assertSee('Push Day');
        $response->assertSee('Upper body push exercises');
    }

    public function test_index_displays_exercise_count()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id, 'name' => 'Leg Day']);
        $exercise1 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise2 = Exercise::factory()->create(['user_id' => $user->id]);
        $exercise3 = Exercise::factory()->create(['user_id' => $user->id]);
        
        $template->exercises()->attach($exercise1->id, ['order' => 1]);
        $template->exercises()->attach($exercise2->id, ['order' => 2]);
        $template->exercises()->attach($exercise3->id, ['order' => 3]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('3 exercises');
    }

    public function test_index_displays_creation_date()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create([
            'user_id' => $user->id,
            'name' => 'Full Body',
            'created_at' => now()->subDays(10)
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee($template->created_at->format('M j, Y'));
    }

    public function test_index_displays_action_buttons()
    {
        $user = User::factory()->create();
        $template = WorkoutTemplate::factory()->create(['user_id' => $user->id, 'name' => 'Test Template']);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        // Check for edit and delete buttons
        $response->assertSee(route('workout-templates.edit', $template));
        $response->assertSee(route('workout-templates.destroy', $template));
    }

    public function test_index_displays_create_button()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Create New Template');
        $response->assertSee(route('workout-templates.create'));
    }

    public function test_index_displays_empty_state_when_no_templates()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('No templates found. Create one to get started!');
    }

    public function test_index_only_shows_user_own_templates()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $template1 = WorkoutTemplate::factory()->create(['user_id' => $user1->id, 'name' => 'User 1 Template']);
        $template2 = WorkoutTemplate::factory()->create(['user_id' => $user2->id, 'name' => 'User 2 Template']);

        $response = $this->actingAs($user1)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('User 1 Template');
        $response->assertDontSee('User 2 Template');
    }

    public function test_index_truncates_long_descriptions()
    {
        $user = User::factory()->create();
        $longDescription = str_repeat('This is a very long description. ', 20);
        $template = WorkoutTemplate::factory()->create([
            'user_id' => $user->id,
            'name' => 'Template',
            'description' => $longDescription
        ]);

        $response = $this->actingAs($user)->get(route('workout-templates.index'));

        $response->assertStatus(200);
        // Should see truncated version with ellipsis
        $response->assertSee('...');
    }

    public function test_index_displays_success_message_after_create()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['success' => 'Template created successfully'])
            ->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Template created successfully');
    }

    public function test_index_displays_success_message_after_update()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['success' => 'Template updated successfully'])
            ->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Template updated successfully');
    }

    public function test_index_displays_success_message_after_delete()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['success' => 'Template deleted successfully'])
            ->get(route('workout-templates.index'));

        $response->assertStatus(200);
        $response->assertSee('Template deleted successfully');
    }

    public function test_index_requires_authentication()
    {
        $response = $this->get(route('workout-templates.index'));

        $response->assertRedirect(route('login'));
    }
}
