<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\User;
use App\Services\ExerciseAliasService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExerciseAliasServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExerciseAliasService $service;
    private User $user;
    private Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new ExerciseAliasService();
        $this->user = User::factory()->create();
        $this->exercise = Exercise::factory()->create([
            'title' => 'Bench Press',
            'user_id' => null
        ]);
    }

    /** @test */
    public function create_alias_creates_alias_with_correct_data()
    {
        $aliasName = 'BP';
        
        $alias = $this->service->createAlias($this->user, $this->exercise, $aliasName);
        
        $this->assertInstanceOf(ExerciseAlias::class, $alias);
        $this->assertEquals($this->user->id, $alias->user_id);
        $this->assertEquals($this->exercise->id, $alias->exercise_id);
        $this->assertEquals($aliasName, $alias->alias_name);
        
        $this->assertDatabaseHas('exercise_aliases', [
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => $aliasName
        ]);
    }

    /** @test */
    public function create_alias_invalidates_cache_for_user()
    {
        // Pre-populate cache
        $this->service->getUserAliases($this->user);
        
        // Create new alias
        $this->service->createAlias($this->user, $this->exercise, 'BP');
        
        // Get aliases again - should fetch from database, not cache
        $aliases = $this->service->getUserAliases($this->user);
        
        $this->assertCount(1, $aliases);
        $this->assertTrue($aliases->has($this->exercise->id));
    }

    /** @test */
    public function create_alias_returns_existing_alias_on_duplicate()
    {
        // Create first alias
        $firstAlias = $this->service->createAlias($this->user, $this->exercise, 'BP');
        
        // Attempt to create duplicate
        $secondAlias = $this->service->createAlias($this->user, $this->exercise, 'BP');
        
        $this->assertEquals($firstAlias->id, $secondAlias->id);
        $this->assertEquals($firstAlias->alias_name, $secondAlias->alias_name);
        
        // Should only have one alias in database
        $this->assertCount(1, ExerciseAlias::where('user_id', $this->user->id)
            ->where('exercise_id', $this->exercise->id)
            ->get());
    }

    /** @test */
    public function get_user_aliases_returns_keyed_collection()
    {
        $exercise1 = Exercise::factory()->create(['title' => 'Exercise 1']);
        $exercise2 = Exercise::factory()->create(['title' => 'Exercise 2']);
        
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'E1'
        ]);
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'E2'
        ]);
        
        $aliases = $this->service->getUserAliases($this->user);
        
        $this->assertInstanceOf(Collection::class, $aliases);
        $this->assertCount(2, $aliases);
        $this->assertTrue($aliases->has($exercise1->id));
        $this->assertTrue($aliases->has($exercise2->id));
        $this->assertEquals('E1', $aliases->get($exercise1->id)->alias_name);
        $this->assertEquals('E2', $aliases->get($exercise2->id)->alias_name);
    }

    /** @test */
    public function get_user_aliases_returns_empty_collection_when_no_aliases()
    {
        $aliases = $this->service->getUserAliases($this->user);
        
        $this->assertInstanceOf(Collection::class, $aliases);
        $this->assertCount(0, $aliases);
    }

    /** @test */
    public function get_user_aliases_uses_request_level_cache()
    {
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        // First call - should query database
        $aliases1 = $this->service->getUserAliases($this->user);
        
        // Create another alias directly in database (bypassing service)
        $newExercise = Exercise::factory()->create();
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $newExercise->id,
            'alias_name' => 'New'
        ]);
        
        // Second call - should use cache, not see new alias
        $aliases2 = $this->service->getUserAliases($this->user);
        
        $this->assertCount(1, $aliases2);
        $this->assertFalse($aliases2->has($newExercise->id));
        
        // Clear cache and verify new alias is now visible
        $this->service->clearCache();
        $aliases3 = $this->service->getUserAliases($this->user);
        
        $this->assertCount(2, $aliases3);
        $this->assertTrue($aliases3->has($newExercise->id));
    }

    /** @test */
    public function apply_aliases_to_exercises_modifies_exercise_titles()
    {
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press']);
        $exercise2 = Exercise::factory()->create(['title' => 'Squat']);
        $exercise3 = Exercise::factory()->create(['title' => 'Deadlift']);
        
        // Create aliases for exercise1 and exercise2 only
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'BP'
        ]);
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'SQ'
        ]);
        
        $exercises = collect([$exercise1, $exercise2, $exercise3]);
        
        $result = $this->service->applyAliasesToExercises($exercises, $this->user);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        
        // Check that aliases were applied
        $this->assertEquals('BP', $result->get(0)->title);
        $this->assertEquals('SQ', $result->get(1)->title);
        $this->assertEquals('Deadlift', $result->get(2)->title); // No alias
    }

    /** @test */
    public function apply_aliases_to_exercises_returns_empty_collection_for_empty_input()
    {
        $exercises = collect([]);
        
        $result = $this->service->applyAliasesToExercises($exercises, $this->user);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /** @test */
    public function get_display_name_returns_alias_when_exists()
    {
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $displayName = $this->service->getDisplayName($this->exercise, $this->user);
        
        $this->assertEquals('BP', $displayName);
    }

    /** @test */
    public function get_display_name_returns_title_when_no_alias()
    {
        $displayName = $this->service->getDisplayName($this->exercise, $this->user);
        
        $this->assertEquals('Bench Press', $displayName);
    }

    /** @test */
    public function get_display_name_handles_errors_gracefully()
    {
        // Create a mock service that will throw an exception
        $mockService = $this->getMockBuilder(ExerciseAliasService::class)
            ->onlyMethods(['getUserAliases'])
            ->getMock();
        
        $mockService->expects($this->once())
            ->method('getUserAliases')
            ->willThrowException(new \Exception('Database error'));
        
        Log::shouldReceive('error')
            ->once()
            ->with('Alias lookup failed', \Mockery::type('array'));
        
        $displayName = $mockService->getDisplayName($this->exercise, $this->user);
        
        // Should fallback to exercise title
        $this->assertEquals('Bench Press', $displayName);
    }

    /** @test */
    public function has_alias_returns_true_when_alias_exists()
    {
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $hasAlias = $this->service->hasAlias($this->user, $this->exercise);
        
        $this->assertTrue($hasAlias);
    }

    /** @test */
    public function has_alias_returns_false_when_no_alias()
    {
        $hasAlias = $this->service->hasAlias($this->user, $this->exercise);
        
        $this->assertFalse($hasAlias);
    }

    /** @test */
    public function has_alias_returns_false_for_different_user()
    {
        $otherUser = User::factory()->create();
        
        ExerciseAlias::factory()->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $hasAlias = $this->service->hasAlias($this->user, $this->exercise);
        
        $this->assertFalse($hasAlias);
    }

    /** @test */
    public function delete_alias_removes_alias_from_database()
    {
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $result = $this->service->deleteAlias($alias);
        
        $this->assertTrue($result);
        $this->assertSoftDeleted($alias);
    }

    /** @test */
    public function delete_alias_invalidates_cache_for_user()
    {
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        // Pre-populate cache
        $aliases = $this->service->getUserAliases($this->user);
        $this->assertCount(1, $aliases);
        
        // Delete alias
        $this->service->deleteAlias($alias);
        
        // Get aliases again - should fetch from database, not cache
        $aliases = $this->service->getUserAliases($this->user);
        
        $this->assertCount(0, $aliases);
    }

    /** @test */
    public function clear_cache_removes_all_cached_aliases()
    {
        $user2 = User::factory()->create();
        
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $exercise2 = Exercise::factory()->create();
        ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'SQ'
        ]);
        
        // Populate cache for both users
        $this->service->getUserAliases($this->user);
        $this->service->getUserAliases($user2);
        
        // Clear cache
        $this->service->clearCache();
        
        // Create new aliases directly in database
        $exercise3 = Exercise::factory()->create();
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $exercise3->id,
            'alias_name' => 'DL'
        ]);
        
        // Should see new alias since cache was cleared
        $aliases = $this->service->getUserAliases($this->user);
        $this->assertCount(2, $aliases);
        $this->assertTrue($aliases->has($exercise3->id));
    }

    /** @test */
    public function multiple_users_can_have_same_alias_name_for_different_exercises()
    {
        $user2 = User::factory()->create();
        $exercise2 = Exercise::factory()->create(['title' => 'Squat']);
        
        // Both users create alias with same name for different exercises
        $alias1 = $this->service->createAlias($this->user, $this->exercise, 'My Exercise');
        $alias2 = $this->service->createAlias($user2, $exercise2, 'My Exercise');
        
        $this->assertNotEquals($alias1->id, $alias2->id);
        $this->assertEquals('My Exercise', $alias1->alias_name);
        $this->assertEquals('My Exercise', $alias2->alias_name);
        $this->assertEquals($this->exercise->id, $alias1->exercise_id);
        $this->assertEquals($exercise2->id, $alias2->exercise_id);
    }

    /** @test */
    public function get_user_aliases_only_returns_aliases_for_specified_user()
    {
        $user2 = User::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        ExerciseAlias::factory()->create([
            'user_id' => $this->user->id,
            'exercise_id' => $this->exercise->id,
            'alias_name' => 'BP'
        ]);
        ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'SQ'
        ]);
        
        $user1Aliases = $this->service->getUserAliases($this->user);
        $user2Aliases = $this->service->getUserAliases($user2);
        
        $this->assertCount(1, $user1Aliases);
        $this->assertCount(1, $user2Aliases);
        $this->assertTrue($user1Aliases->has($this->exercise->id));
        $this->assertTrue($user2Aliases->has($exercise2->id));
        $this->assertFalse($user1Aliases->has($exercise2->id));
        $this->assertFalse($user2Aliases->has($this->exercise->id));
    }
}
