<?php

namespace Tests\Unit\Models;

use App\Models\Exercise;
use App\Models\LiftLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiftLogMetaCastTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_meta_round_trips_through_the_array_cast(): void
    {
        $meta = ['v' => 1, 'complex' => ['id' => 'clean_complex_1']];

        $log = LiftLog::create([
            'exercise_id' => $this->exercise->id,
            'user_id' => $this->user->id,
            'logged_at' => now(),
            'meta' => $meta,
        ]);

        $fresh = $log->fresh();

        $this->assertIsArray($fresh->meta);
        $this->assertSame($meta, $fresh->meta);
    }

    public function test_meta_defaults_to_null_when_not_set(): void
    {
        $log = LiftLog::create([
            'exercise_id' => $this->exercise->id,
            'user_id' => $this->user->id,
            'logged_at' => now(),
        ]);

        $this->assertNull($log->fresh()->meta);
    }
}
