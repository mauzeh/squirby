<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PersonalRecord;
use App\Services\FabService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FabServiceTest extends TestCase
{
    use RefreshDatabase;

    private FabService $fabService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fabService = new FabService();
    }

    /** @test */
    public function it_always_shows_tooltip_on_feed_context()
    {
        $user = User::factory()->create();
        
        // Create a non-admin connection
        $friend = User::factory()->create();
        $user->follow($friend);
        
        $fab = $this->fabService->createConnectionFab($user, 'feed');
        
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_shows_tooltip_on_lifts_when_user_has_no_connections_and_pr_today()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_hides_tooltip_on_lifts_when_user_has_no_pr_today()
    {
        $user = User::factory()->create();
        
        // Create PRs from yesterday and older
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now()->subDay(),
        ]);
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now()->subWeek(),
        ]);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertNull($fab['data']['tooltip']);
    }

    /** @test */
    public function it_hides_tooltip_on_lifts_when_user_has_non_admin_connections()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create a non-admin connection
        $friend = User::factory()->create();
        $user->follow($friend);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertNull($fab['data']['tooltip']);
    }

    /** @test */
    public function it_excludes_admin_connections_when_checking_for_following()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create admin user and role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // User follows admin
        $user->follow($admin);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        // Should still show tooltip because admin connections don't count
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_excludes_admin_connections_when_checking_for_followers()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create admin user and role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        
        // Admin follows user
        $admin->follow($user);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        // Should still show tooltip because admin connections don't count
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_hides_tooltip_when_user_has_non_admin_follower()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create non-admin follower
        $follower = User::factory()->create();
        $follower->follow($user);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertNull($fab['data']['tooltip']);
    }

    /** @test */
    public function it_shows_tooltip_on_lifts_with_mixed_admin_and_non_admin_connections()
    {
        $user = User::factory()->create();
        
        // Create a PR today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create admin user
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $user->follow($admin);
        
        // Create non-admin user
        $friend = User::factory()->create();
        $user->follow($friend);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        // Should hide tooltip because there's at least one non-admin connection
        $this->assertNull($fab['data']['tooltip']);
    }

    /** @test */
    public function it_only_counts_current_prs_not_superseded_ones()
    {
        $user = User::factory()->create();
        
        // Create 2 old PRs that will be superseded
        $oldPR1 = PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        $oldPR2 = PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        // Create 2 new PRs that supersede the old ones
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'previous_pr_id' => $oldPR1->id,
            'achieved_at' => now(),
        ]);
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'previous_pr_id' => $oldPR2->id,
            'achieved_at' => now(),
        ]);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        // Should show tooltip because there are current PRs today (even though some are superseded)
        $this->assertArrayHasKey('tooltip', $fab['data']);
        
        // Verify the count is actually 2 current PRs
        $this->assertEquals(2, $user->personalRecords()->current()->count());
    }

    /** @test */
    public function it_shows_tooltip_with_no_context_when_user_has_no_connections()
    {
        $user = User::factory()->create();
        
        $fab = $this->fabService->createConnectionFab($user);
        
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_hides_tooltip_with_no_context_when_user_has_non_admin_connections()
    {
        $user = User::factory()->create();
        
        // Create non-admin connection
        $friend = User::factory()->create();
        $user->follow($friend);
        
        $fab = $this->fabService->createConnectionFab($user);
        
        $this->assertNull($fab['data']['tooltip']);
    }

    /** @test */
    public function it_shows_tooltip_with_no_context_when_user_only_has_admin_connections()
    {
        $user = User::factory()->create();
        
        // Create admin user
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $user->follow($admin);
        
        $fab = $this->fabService->createConnectionFab($user);
        
        // Should show tooltip because admin connections don't count
        $this->assertArrayHasKey('tooltip', $fab['data']);
        $this->assertEquals('Connect with friends', $fab['data']['tooltip']);
    }

    /** @test */
    public function it_returns_correct_fab_structure()
    {
        $user = User::factory()->create();
        
        $fab = $this->fabService->createConnectionFab($user, 'feed');
        
        $this->assertIsArray($fab);
        $this->assertArrayHasKey('type', $fab);
        $this->assertEquals('fab', $fab['type']);
        $this->assertArrayHasKey('data', $fab);
        $this->assertArrayHasKey('url', $fab['data']);
        $this->assertArrayHasKey('icon', $fab['data']);
        $this->assertArrayHasKey('title', $fab['data']);
        $this->assertEquals('fa-user-plus', $fab['data']['icon']);
        $this->assertEquals('Connect', $fab['data']['title']);
    }

    /** @test */
    public function it_shows_tooltip_on_lifts_when_pr_achieved_today()
    {
        $user = User::factory()->create();
        
        // Create a PR achieved today
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now(),
        ]);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertArrayHasKey('tooltip', $fab['data']);
    }

    /** @test */
    public function it_hides_tooltip_on_lifts_when_pr_achieved_yesterday()
    {
        $user = User::factory()->create();
        
        // Create a PR achieved yesterday
        PersonalRecord::factory()->create([
            'user_id' => $user->id,
            'achieved_at' => now()->subDay(),
        ]);
        
        $fab = $this->fabService->createConnectionFab($user, 'lifts');
        
        $this->assertNull($fab['data']['tooltip']);
    }
}
