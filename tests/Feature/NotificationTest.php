<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\PRComment;
use App\Models\PRHighFive;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected PersonalRecord $pr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'title' => 'Bench Press',
            'show_in_feed' => true,
        ]);

        $this->pr = PersonalRecord::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise->id,
            'achieved_at' => now(),
        ]);
    }

    /** @test */
    public function it_creates_notification_when_someone_comments_on_pr()
    {
        $comment = PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'pr_comment',
            'actor_id' => $this->otherUser->id,
            'notifiable_type' => PRComment::class,
            'notifiable_id' => $comment->id,
        ]);

        $notification = $this->user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('pr_comment', $notification->type);
        $this->assertEquals($this->otherUser->id, $notification->actor_id);
        $this->assertTrue($notification->isUnread());
    }

    /** @test */
    public function it_does_not_create_notification_when_commenting_on_own_pr()
    {
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->user->id,
            'comment' => 'My own comment',
        ]);

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    /** @test */
    public function it_creates_notification_when_someone_high_fives_pr()
    {
        $highFive = PRHighFive::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $this->pr->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'pr_high_five',
            'actor_id' => $this->otherUser->id,
            'notifiable_type' => PRHighFive::class,
            'notifiable_id' => $highFive->id,
        ]);
    }

    /** @test */
    public function it_does_not_create_notification_when_high_fiving_own_pr()
    {
        PRHighFive::create([
            'user_id' => $this->user->id,
            'personal_record_id' => $this->pr->id,
        ]);

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    /** @test */
    public function it_deletes_notification_when_high_five_is_removed()
    {
        $highFive = PRHighFive::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $this->pr->id,
        ]);

        $this->assertEquals(1, $this->user->notifications()->count());

        $highFive->delete();

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    /** @test */
    public function it_deletes_notification_when_comment_is_removed()
    {
        $comment = PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        $this->assertEquals(1, $this->user->notifications()->count());

        $comment->delete();

        $this->assertEquals(0, $this->user->notifications()->count());
    }

    /** @test */
    public function it_displays_notifications_page()
    {
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertSee($this->otherUser->name);
        $response->assertSee('commented on your PR');
    }

    /** @test */
    public function it_shows_unread_count_in_title()
    {
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('1 unread');
    }

    /** @test */
    public function it_auto_marks_all_notifications_as_read_on_view()
    {
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        PRHighFive::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $this->pr->id,
        ]);

        $this->assertEquals(2, $this->user->notifications()->unread()->count());

        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $this->assertEquals(0, $this->user->notifications()->unread()->count());
        $this->assertEquals(2, $this->user->notifications()->read()->count());
    }

    /** @test */
    public function it_shows_empty_state_when_no_notifications()
    {
        $response = $this->actingAs($this->user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertSee('Nothing to see here');
    }

    /** @test */
    public function notification_includes_comment_preview_in_data()
    {
        $comment = PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'This is a great PR! Keep up the good work!',
        ]);

        $notification = $this->user->notifications()->first();
        
        $this->assertArrayHasKey('comment_preview', $notification->data);
        $this->assertArrayHasKey('personal_record_id', $notification->data);
        $this->assertEquals($this->pr->id, $notification->data['personal_record_id']);
    }

    /** @test */
    public function notification_includes_pr_id_for_high_five()
    {
        PRHighFive::create([
            'user_id' => $this->otherUser->id,
            'personal_record_id' => $this->pr->id,
        ]);

        $notification = $this->user->notifications()->first();
        
        $this->assertArrayHasKey('personal_record_id', $notification->data);
        $this->assertEquals($this->pr->id, $notification->data['personal_record_id']);
    }

    /** @test */
    public function it_loads_notification_relationships()
    {
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Great job!',
        ]);

        $notification = Notification::with(['actor', 'notifiable'])->first();

        $this->assertInstanceOf(User::class, $notification->actor);
        $this->assertEquals($this->otherUser->id, $notification->actor->id);
        $this->assertInstanceOf(PRComment::class, $notification->notifiable);
    }

    /** @test */
    public function recent_scope_only_returns_last_30_days()
    {
        // Create old notification
        $oldComment = PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Old comment',
        ]);
        
        $oldNotification = $this->user->notifications()->first();
        // Use DB query to bypass model timestamps protection
        \DB::table('notifications')
            ->where('id', $oldNotification->id)
            ->update(['created_at' => now()->subDays(31)]);

        // Create recent notification
        PRComment::create([
            'personal_record_id' => $this->pr->id,
            'user_id' => $this->otherUser->id,
            'comment' => 'Recent comment',
        ]);

        $recentNotifications = $this->user->notifications()->recent()->get();
        
        $this->assertEquals(1, $recentNotifications->count());
        $this->assertEquals('Recent comment', $recentNotifications->first()->data['comment_preview']);
    }
}
