<?php

namespace Tests\Feature\Sync;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $token = $this->user->createToken('test-device')->plainTextToken;
        $this->headers = ['Authorization' => 'Bearer '.$token, 'X-Device-Id' => 'device-123'];
    }

    public function test_posted_meta_is_echoed_verbatim_on_restore(): void
    {
        $meta = ['v' => 1, 'complex' => ['id' => 'clean_complex_1']];

        $this->withHeaders($this->headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Clean Complex',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'meta' => $meta,
            'sets' => [
                ['weight' => 135, 'reps' => 3],
            ],
        ])->assertStatus(200);

        $logs = $this->withHeaders($this->headers)->getJson('/api/sync/restore')->assertStatus(200)->json('logs');

        $this->assertCount(1, $logs);
        $this->assertSame($meta, $logs[0]['meta']);
    }

    public function test_missing_meta_is_echoed_as_null_on_restore(): void
    {
        $this->withHeaders($this->headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Bench Press',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'sets' => [
                ['weight' => 135, 'reps' => 5],
            ],
        ])->assertStatus(200);

        $logs = $this->withHeaders($this->headers)->getJson('/api/sync/restore')->assertStatus(200)->json('logs');

        $this->assertCount(1, $logs);
        $this->assertArrayHasKey('meta', $logs[0]);
        $this->assertNull($logs[0]['meta']);
    }

    public function test_posted_meta_is_echoed_verbatim_on_changes(): void
    {
        $meta = ['v' => 1, 'complex' => ['id' => 'clean_complex_1']];

        $this->withHeaders($this->headers)->postJson('/api/sync/logs', [
            'exercise_name' => 'Clean Complex',
            'date' => '2026-06-15',
            'log_type' => 'barbell',
            'weight_unit' => 'lbs',
            'meta' => $meta,
            'sets' => [
                ['weight' => 135, 'reps' => 3],
            ],
        ])->assertStatus(200);

        $logs = $this->withHeaders($this->headers)->getJson('/api/sync/changes')->assertStatus(200)->json('logs');

        $this->assertCount(1, $logs);
        $this->assertSame($meta, $logs[0]['meta']);
    }
}
