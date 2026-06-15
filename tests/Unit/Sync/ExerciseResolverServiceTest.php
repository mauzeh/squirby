<?php

namespace Tests\Unit\Sync;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\User;
use App\Sync\Services\ExerciseResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseResolverService $resolver;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ExerciseResolverService();
        $this->user = User::factory()->create();
    }

    public function test_exact_canonical_name_match(): void
    {
        $exercise = Exercise::create([
            'title' => 'Bench Press',
            'canonical_name' => 'bench_press',
            'exercise_type' => 'regular',
        ]);

        $resolved = $this->resolver->resolve('Bench Press', $this->user);
        $this->assertEquals($exercise->id, $resolved->id);

        $resolvedSnake = $this->resolver->resolve('bench_press', $this->user);
        $this->assertEquals($exercise->id, $resolvedSnake->id);
    }

    public function test_case_insensitive_title_match(): void
    {
        $exercise = Exercise::create([
            'title' => 'Deadlift',
            'canonical_name' => 'deadlift',
            'exercise_type' => 'regular',
        ]);

        $resolved = $this->resolver->resolve('deadlift', $this->user);
        $this->assertEquals($exercise->id, $resolved->id);

        $resolvedUpper = $this->resolver->resolve('DEADLIFT', $this->user);
        $this->assertEquals($exercise->id, $resolvedUpper->id);
    }

    public function test_case_insensitive_alias_match(): void
    {
        $exercise = Exercise::create([
            'title' => 'Back Squat',
            'canonical_name' => 'back_squat',
            'exercise_type' => 'regular',
        ]);

        ExerciseAlias::create([
            'exercise_id' => $exercise->id,
            'alias_name' => 'Squats',
            'user_id' => $this->user->id,
        ]);

        $resolved = $this->resolver->resolve('squats', $this->user);
        $this->assertEquals($exercise->id, $resolved->id);

        $resolvedCaps = $this->resolver->resolve('SQUATS', $this->user);
        $this->assertEquals($exercise->id, $resolvedCaps->id);
    }

    public function test_scoping_to_user_and_global(): void
    {
        // 1. User specific exercise
        $userExercise = Exercise::create([
            'title' => 'User Push Up',
            'canonical_name' => 'user_push_up',
            'exercise_type' => 'bodyweight',
            'user_id' => $this->user->id,
        ]);

        // 2. Another user specific exercise
        $otherUser = User::factory()->create();
        $otherExercise = Exercise::create([
            'title' => 'Other Push Up',
            'canonical_name' => 'other_push_up',
            'exercise_type' => 'bodyweight',
            'user_id' => $otherUser->id,
        ]);

        // Resolving user's own exercise should succeed
        $resolved = $this->resolver->resolve('user_push_up', $this->user);
        $this->assertEquals($userExercise->id, $resolved->id);

        // Resolving other user's exercise should NOT match, and instead auto-create a new one
        $resolvedOther = $this->resolver->resolve('other_push_up', $this->user);
        $this->assertNotEquals($otherExercise->id, $resolvedOther->id);
        $this->assertEquals('other_push_up', $resolvedOther->canonical_name);
        $this->assertNull($resolvedOther->user_id); // Auto-created exercises are global (user_id = NULL)
    }

    public function test_soft_deleted_exclusion(): void
    {
        $exercise = Exercise::create([
            'title' => 'Soft Deleted Pull Up',
            'canonical_name' => 'soft_deleted_pull_up',
            'exercise_type' => 'bodyweight',
        ]);

        $exercise->delete(); // Soft delete

        // Should auto-create a new one instead of returning soft-deleted
        $resolved = $this->resolver->resolve('Soft Deleted Pull Up', $this->user);
        $this->assertNotEquals($exercise->id, $resolved->id);
        $this->assertNull($resolved->deleted_at);
    }

    public function test_auto_creation_with_type_derivation(): void
    {
        // cardio log type should derive cardio exercise type
        $cardio = $this->resolver->resolve('Super Run', $this->user, 'cardio');
        $this->assertEquals('Super Run', $cardio->title);
        $this->assertEquals('cardio', $cardio->exercise_type);

        // bodyweight log type should derive bodyweight exercise type
        $bodyweight = $this->resolver->resolve('Super Push', $this->user, 'bodyweight');
        $this->assertEquals('bodyweight', $bodyweight->exercise_type);

        // barbell log type should derive regular exercise type
        $regular = $this->resolver->resolve('Super Press', $this->user, 'barbell');
        $this->assertEquals('regular', $regular->exercise_type);
    }
}
