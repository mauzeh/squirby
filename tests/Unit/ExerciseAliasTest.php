<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseAliasTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_relationship_returns_correct_user()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Test Alias'
        ]);

        $this->assertInstanceOf(User::class, $alias->user);
        $this->assertEquals($user->id, $alias->user->id);
        $this->assertEquals($user->email, $alias->user->email);
    }

    /** @test */
    public function exercise_relationship_returns_correct_exercise()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['title' => 'Bench Press']);
        
        $alias = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);

        $this->assertInstanceOf(Exercise::class, $alias->exercise);
        $this->assertEquals($exercise->id, $alias->exercise->id);
        $this->assertEquals('Bench Press', $alias->exercise->title);
    }

    /** @test */
    public function for_user_scope_returns_only_aliases_for_specified_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'User 1 Alias'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'User 2 Alias'
        ]);

        $user1Aliases = ExerciseAlias::forUser($user1->id)->get();
        $user2Aliases = ExerciseAlias::forUser($user2->id)->get();

        $this->assertCount(1, $user1Aliases);
        $this->assertCount(1, $user2Aliases);
        $this->assertTrue($user1Aliases->contains($alias1));
        $this->assertFalse($user1Aliases->contains($alias2));
        $this->assertTrue($user2Aliases->contains($alias2));
        $this->assertFalse($user2Aliases->contains($alias1));
    }

    /** @test */
    public function for_user_scope_returns_empty_collection_when_user_has_no_aliases()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // Create alias for other user
        ExerciseAlias::factory()->create([
            'user_id' => $otherUser->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Other User Alias'
        ]);

        $userAliases = ExerciseAlias::forUser($user->id)->get();

        $this->assertCount(0, $userAliases);
    }

    /** @test */
    public function for_exercise_scope_returns_only_aliases_for_specified_exercise()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise1 = Exercise::factory()->create(['title' => 'Bench Press']);
        $exercise2 = Exercise::factory()->create(['title' => 'Squat']);
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'BP'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'Bench'
        ]);
        
        $alias3 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'SQ'
        ]);

        $exercise1Aliases = ExerciseAlias::forExercise($exercise1->id)->get();
        $exercise2Aliases = ExerciseAlias::forExercise($exercise2->id)->get();

        $this->assertCount(2, $exercise1Aliases);
        $this->assertCount(1, $exercise2Aliases);
        $this->assertTrue($exercise1Aliases->contains($alias1));
        $this->assertTrue($exercise1Aliases->contains($alias2));
        $this->assertFalse($exercise1Aliases->contains($alias3));
        $this->assertTrue($exercise2Aliases->contains($alias3));
    }

    /** @test */
    public function for_exercise_scope_returns_empty_collection_when_exercise_has_no_aliases()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        $otherExercise = Exercise::factory()->create();
        
        // Create alias for other exercise
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $otherExercise->id,
            'alias_name' => 'Other Exercise Alias'
        ]);

        $exerciseAliases = ExerciseAlias::forExercise($exercise->id)->get();

        $this->assertCount(0, $exerciseAliases);
    }

    /** @test */
    public function unique_constraint_prevents_duplicate_user_exercise_combination()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        // Create first alias
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'First Alias'
        ]);

        // Attempt to create duplicate with different alias name
        $this->expectException(QueryException::class);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'Second Alias'
        ]);
    }

    /** @test */
    public function unique_constraint_allows_same_user_with_different_exercises()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'Alias 1'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'Alias 2'
        ]);

        $this->assertNotEquals($alias1->id, $alias2->id);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias1->id]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id]);
    }

    /** @test */
    public function unique_constraint_allows_different_users_with_same_exercise()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 1 Alias'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 2 Alias'
        ]);

        $this->assertNotEquals($alias1->id, $alias2->id);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias1->id]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id]);
    }

    /** @test */
    public function cascade_delete_removes_aliases_when_user_is_deleted()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'Alias 1'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'Alias 2'
        ]);

        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias1->id]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id]);

        // Delete user
        $user->delete();

        // Refresh aliases to get the deleted_at timestamp
        $alias1->refresh();
        $alias2->refresh();

        // Aliases should be soft deleted
        $this->assertSoftDeleted($alias1);
        $this->assertSoftDeleted($alias2);
        $this->assertCount(2, ExerciseAlias::withTrashed()->get());
        $this->assertCount(0, ExerciseAlias::all());
    }

    /** @test */
    public function cascade_delete_removes_aliases_when_exercise_is_deleted()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 1 Alias'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 2 Alias'
        ]);

        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias1->id]);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id]);

        // Delete exercise
        $exercise->delete();

        // Refresh aliases to get the deleted_at timestamp
        $alias1->refresh();
        $alias2->refresh();

        // Aliases should be soft deleted
        $this->assertSoftDeleted($alias1);
        $this->assertSoftDeleted($alias2);
        $this->assertCount(2, ExerciseAlias::withTrashed()->get());
        $this->assertCount(0, ExerciseAlias::all());
    }

    /** @test */
    public function cascade_delete_only_removes_aliases_for_deleted_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $exercise = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user1->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 1 Alias'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user2->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'User 2 Alias'
        ]);

        // Delete user1
        $user1->delete();

        // Refresh alias to get the deleted_at timestamp
        $alias1->refresh();

        // Only user1's alias should be soft deleted
        $this->assertSoftDeleted($alias1);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id, 'deleted_at' => null]);
        $this->assertCount(2, ExerciseAlias::withTrashed()->get());
        $this->assertCount(1, ExerciseAlias::all());
    }

    /** @test */
    public function cascade_delete_only_removes_aliases_for_deleted_exercise()
    {
        $user = User::factory()->create();
        $exercise1 = Exercise::factory()->create();
        $exercise2 = Exercise::factory()->create();
        
        $alias1 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise1->id,
            'alias_name' => 'Exercise 1 Alias'
        ]);
        
        $alias2 = ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise2->id,
            'alias_name' => 'Exercise 2 Alias'
        ]);

        // Delete exercise1
        $exercise1->delete();

        // Refresh alias to get the deleted_at timestamp
        $alias1->refresh();

        // Only exercise1's alias should be soft deleted
        $this->assertSoftDeleted($alias1);
        $this->assertDatabaseHas('exercise_aliases', ['id' => $alias2->id, 'deleted_at' => null]);
        $this->assertCount(2, ExerciseAlias::withTrashed()->get());
        $this->assertCount(1, ExerciseAlias::all());
    }
}
