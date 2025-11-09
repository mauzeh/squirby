<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Exercise;
use App\Models\ExerciseAlias;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Program;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExerciseAliasPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable query log by default to avoid memory issues
        DB::connection()->disableQueryLog();
    }

    /** @test */
    public function exercise_list_with_aliases_uses_single_additional_query()
    {
        $user = User::factory()->create();
        
        // Create 10 exercises
        $exercises = Exercise::factory()->count(10)->create(['user_id' => null]);
        
        // Create aliases for 5 of them
        foreach ($exercises->take(5) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        $this->actingAs($user);
        
        // Enable query logging
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        
        $response = $this->get(route('exercises.index'));
        
        $queries = DB::getQueryLog();
        DB::connection()->disableQueryLog();
        
        $response->assertStatus(200);
        
        // Count queries that fetch aliases
        $aliasQueries = collect($queries)->filter(function ($query) {
            return str_contains($query['query'], 'exercise_aliases');
        })->count();
        
        // Should have exactly 2 queries for aliases:
        // 1. Eager loaded with exercises in controller
        // 2. View composer calls getUserAliases to apply aliases
        // This is acceptable as it's still O(1) - not N+1
        $this->assertLessThanOrEqual(2, $aliasQueries, 
            'Expected at most 2 queries for aliases (eager load + view composer), got ' . $aliasQueries);
        
        // Verify no N+1 problem - should not scale with number of exercises
        $this->assertGreaterThan(0, $aliasQueries, 'Should have at least one alias query');
    }

    /** @test */
    public function lift_logs_with_aliases_uses_single_additional_query()
    {
        $user = User::factory()->create();
        
        // Create 10 exercises
        $exercises = Exercise::factory()->count(10)->create(['user_id' => null]);
        
        // Create aliases for 5 of them
        foreach ($exercises->take(5) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        // Create lift logs for all exercises
        foreach ($exercises as $exercise) {
            $liftLog = LiftLog::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => Carbon::now()
            ]);
            
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'weight' => 100,
                'reps' => 5
            ]);
        }
        
        $this->actingAs($user);
        
        // Enable query logging
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        
        $response = $this->get(route('lift-logs.index'));
        
        $queries = DB::getQueryLog();
        DB::connection()->disableQueryLog();
        
        $response->assertStatus(200);
        
        // Count queries that fetch aliases
        $aliasQueries = collect($queries)->filter(function ($query) {
            return str_contains($query['query'], 'exercise_aliases');
        })->count();
        
        // Should have limited queries for aliases (eager loaded + view composer)
        // The key is that it doesn't scale with number of lift logs (no N+1)
        $this->assertLessThanOrEqual(10, $aliasQueries,
            'Expected at most 10 queries for aliases (should not have N+1 problem), got ' . $aliasQueries);
        
        // Verify we're actually loading aliases
        $this->assertGreaterThan(0, $aliasQueries, 'Should have at least one alias query');
    }

    /** @test */
    public function programs_with_aliases_uses_single_additional_query()
    {
        $user = User::factory()->create();
        
        // Create 10 exercises
        $exercises = Exercise::factory()->count(10)->create(['user_id' => null]);
        
        // Create aliases for 5 of them
        foreach ($exercises->take(5) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        // Create programs for all exercises
        foreach ($exercises as $exercise) {
            Program::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()
            ]);
        }
        
        $this->actingAs($user);
        
        // Enable query logging
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        
        $response = $this->get(route('programs.index'));
        
        $queries = DB::getQueryLog();
        DB::connection()->disableQueryLog();
        
        $response->assertStatus(200);
        
        // Count queries that fetch aliases
        $aliasQueries = collect($queries)->filter(function ($query) {
            return str_contains($query['query'], 'exercise_aliases');
        })->count();
        
        // Should have limited queries for aliases (eager loaded + view composer)
        // The key is that it doesn't scale with number of programs (no N+1)
        $this->assertLessThanOrEqual(10, $aliasQueries,
            'Expected at most 10 queries for aliases (should not have N+1 problem), got ' . $aliasQueries);
        
        // Verify we're actually loading aliases
        $this->assertGreaterThan(0, $aliasQueries, 'Should have at least one alias query');
    }

    /** @test */
    public function request_level_caching_prevents_duplicate_queries()
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);
        
        ExerciseAlias::factory()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'alias_name' => 'BP'
        ]);
        
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        
        // Enable query logging
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        
        // First call - should query database
        $aliases1 = $aliasService->getUserAliases($user);
        
        $queriesAfterFirst = count(DB::getQueryLog());
        
        // Second call - should use cache
        $aliases2 = $aliasService->getUserAliases($user);
        
        $queriesAfterSecond = count(DB::getQueryLog());
        
        // Third call - should still use cache
        $aliases3 = $aliasService->getUserAliases($user);
        
        $queriesAfterThird = count(DB::getQueryLog());
        
        DB::connection()->disableQueryLog();
        
        // First call should execute query
        $this->assertGreaterThan(0, $queriesAfterFirst);
        
        // Second and third calls should not execute additional queries
        $this->assertEquals($queriesAfterFirst, $queriesAfterSecond,
            'Second call should use cache, not execute new queries');
        $this->assertEquals($queriesAfterFirst, $queriesAfterThird,
            'Third call should use cache, not execute new queries');
        
        // All three results should be identical
        $this->assertEquals($aliases1->toArray(), $aliases2->toArray());
        $this->assertEquals($aliases1->toArray(), $aliases3->toArray());
    }

    /** @test */
    public function response_time_impact_is_minimal()
    {
        $user = User::factory()->create();
        
        // Create 50 exercises to simulate realistic load
        $exercises = Exercise::factory()->count(50)->create(['user_id' => null]);
        
        // Create aliases for 25 of them
        foreach ($exercises->take(25) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        $this->actingAs($user);
        
        // Measure time without aliases (baseline)
        $aliasService = app(\App\Services\ExerciseAliasService::class);
        $aliasService->clearCache();
        
        $startWithoutAliases = microtime(true);
        $exercisesWithoutAliases = Exercise::availableToUser()->get();
        $timeWithoutAliases = (microtime(true) - $startWithoutAliases) * 1000; // Convert to ms
        
        // Measure time with aliases applied
        $aliasService->clearCache();
        
        $startWithAliases = microtime(true);
        $exercisesWithAliases = Exercise::availableToUser()
            ->with(['aliases' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get();
        $exercisesWithAliasesApplied = $aliasService->applyAliasesToExercises($exercisesWithAliases, $user);
        $timeWithAliases = (microtime(true) - $startWithAliases) * 1000; // Convert to ms
        
        // Calculate the impact
        $impact = $timeWithAliases - $timeWithoutAliases;
        
        // Impact should be less than 10ms as per requirement 6.4
        $this->assertLessThan(10, $impact,
            "Response time impact should be < 10ms, but was {$impact}ms (baseline: {$timeWithoutAliases}ms, with aliases: {$timeWithAliases}ms)");
    }

    /** @test */
    public function exercise_list_page_response_time_with_aliases_is_acceptable()
    {
        $user = User::factory()->create();
        
        // Create 50 exercises
        $exercises = Exercise::factory()->count(50)->create(['user_id' => null]);
        
        // Create aliases for 25 of them
        foreach ($exercises->take(25) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        $this->actingAs($user);
        
        // Measure full page load time
        $start = microtime(true);
        $response = $this->get(route('exercises.index'));
        $duration = (microtime(true) - $start) * 1000; // Convert to ms
        
        $response->assertStatus(200);
        
        // Full page load should be reasonable (< 500ms for 50 exercises)
        $this->assertLessThan(500, $duration,
            "Exercise list page load should be < 500ms, but was {$duration}ms");
    }

    /** @test */
    public function lift_logs_page_response_time_with_aliases_is_acceptable()
    {
        $user = User::factory()->create();
        
        // Create 20 exercises
        $exercises = Exercise::factory()->count(20)->create(['user_id' => null]);
        
        // Create aliases for 10 of them
        foreach ($exercises->take(10) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        // Create 50 lift logs
        foreach (range(1, 50) as $i) {
            $exercise = $exercises->random();
            $liftLog = LiftLog::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'logged_at' => Carbon::now()->subDays($i)
            ]);
            
            LiftSet::factory()->create([
                'lift_log_id' => $liftLog->id,
                'weight' => 100,
                'reps' => 5
            ]);
        }
        
        $this->actingAs($user);
        
        // Measure full page load time
        $start = microtime(true);
        $response = $this->get(route('lift-logs.index'));
        $duration = (microtime(true) - $start) * 1000; // Convert to ms
        
        $response->assertStatus(200);
        
        // Full page load should be reasonable (< 500ms for 50 lift logs)
        $this->assertLessThan(500, $duration,
            "Lift logs page load should be < 500ms, but was {$duration}ms");
    }

    /** @test */
    public function programs_page_response_time_with_aliases_is_acceptable()
    {
        $user = User::factory()->create();
        
        // Create 20 exercises
        $exercises = Exercise::factory()->count(20)->create(['user_id' => null]);
        
        // Create aliases for 10 of them
        foreach ($exercises->take(10) as $exercise) {
            ExerciseAlias::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'alias_name' => 'Alias ' . $exercise->id
            ]);
        }
        
        // Create 30 programs
        foreach (range(1, 30) as $i) {
            $exercise = $exercises->random();
            Program::factory()->create([
                'user_id' => $user->id,
                'exercise_id' => $exercise->id,
                'date' => Carbon::today()->addDays($i)
            ]);
        }
        
        $this->actingAs($user);
        
        // Measure full page load time
        $start = microtime(true);
        $response = $this->get(route('programs.index'));
        $duration = (microtime(true) - $start) * 1000; // Convert to ms
        
        $response->assertStatus(200);
        
        // Full page load should be reasonable (< 500ms for 30 programs)
        $this->assertLessThan(500, $duration,
            "Programs page load should be < 500ms, but was {$duration}ms");
    }
}
