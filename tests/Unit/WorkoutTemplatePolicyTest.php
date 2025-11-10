<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Policies\WorkoutTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutTemplatePolicy $policy;
    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new WorkoutTemplatePolicy();
        
        // Create users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_any_authenticated_user_can_create_templates(): void
    {
        $result = $this->policy->create($this->user);
        
        $this->assertTrue($result);
    }

    public function test_user_can_update_their_own_template(): void
    {
        $template = WorkoutTemplate::create([
            'user_id' => $this->user->id,
            'name' => 'My Template',
            'description' => 'Test description'
        ]);
        
        $result = $this->policy->update($this->user, $template);
        
        $this->assertTrue($result);
    }

    public function test_user_cannot_update_other_users_template(): void
    {
        $template = WorkoutTemplate::create([
            'user_id' => $this->otherUser->id,
            'name' => 'Other Template',
            'description' => 'Test description'
        ]);
        
        $result = $this->policy->update($this->user, $template);
        
        $this->assertFalse($result);
    }

    public function test_user_can_delete_their_own_template(): void
    {
        $template = WorkoutTemplate::create([
            'user_id' => $this->user->id,
            'name' => 'My Template',
            'description' => 'Test description'
        ]);
        
        $result = $this->policy->delete($this->user, $template);
        
        $this->assertTrue($result);
    }

    public function test_user_cannot_delete_other_users_template(): void
    {
        $template = WorkoutTemplate::create([
            'user_id' => $this->otherUser->id,
            'name' => 'Other Template',
            'description' => 'Test description'
        ]);
        
        $result = $this->policy->delete($this->user, $template);
        
        $this->assertFalse($result);
    }
}
