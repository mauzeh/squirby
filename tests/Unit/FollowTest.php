<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_follow_another_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);

        $this->assertTrue($user1->isFollowing($user2));
        $this->assertEquals(1, $user1->following()->count());
        $this->assertEquals(1, $user2->followers()->count());
    }

    /** @test */
    public function user_can_unfollow_another_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);
        $user1->unfollow($user2);

        $this->assertFalse($user1->isFollowing($user2));
        $this->assertEquals(0, $user1->following()->count());
        $this->assertEquals(0, $user2->followers()->count());
    }

    /** @test */
    public function user_cannot_follow_same_user_twice()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);
        $user1->follow($user2); // Try to follow again

        $this->assertEquals(1, $user1->following()->count());
    }

    /** @test */
    public function user_cannot_follow_themselves()
    {
        $user = User::factory()->create();

        $user->follow($user);

        $this->assertFalse($user->isFollowing($user));
        $this->assertEquals(0, $user->following()->count());
    }

    /** @test */
    public function follow_relationship_has_timestamps()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1->follow($user2);

        $follow = Follow::where('follower_id', $user1->id)
            ->where('following_id', $user2->id)
            ->first();

        $this->assertNotNull($follow->created_at);
        $this->assertNotNull($follow->updated_at);
    }

    /** @test */
    public function deleting_user_removes_follow_relationships()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->follow($user2);
        $user2->follow($user3);

        $user2->delete();

        // User1's following should be removed
        $this->assertEquals(0, $user1->following()->count());
        // User3's followers should be removed
        $this->assertEquals(0, $user3->followers()->count());
    }
}
