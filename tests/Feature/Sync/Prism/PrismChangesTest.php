<?php

namespace Tests\Feature\Sync\Prism;

use App\Models\User;
use App\Sync\Models\Gym;
use App\Sync\Models\PrismExerciseOverlayRecord;
use App\Sync\Models\PrismPreference;
use App\Sync\Models\PrismProgram;
use App\Sync\Models\PrismProgramCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrismChangesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gym::create(['id' => 'the-cove', 'name' => 'The Cove']);
    }

    public function test_cold_start_returns_full_gym_state(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t = Carbon::parse('2026-09-01 12:00:00', $tz);

        $program = PrismProgram::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-1',
            'name' => 'Strength Cycle',
            'week_count' => 4,
            'metadata' => ['author' => 'Coach'],
            'device_id' => 'dev-1',
        ]);
        PrismProgram::where('id', $program->id)->update(['updated_at' => $t]);

        $cell1 = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-1',
            'cell_id' => 'w1d1',
            'text' => 'Squat 5x5',
            'device_id' => 'dev-1',
        ]);
        PrismProgramCell::where('id', $cell1->id)->update(['updated_at' => $t]);

        $cell2 = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-1',
            'cell_id' => 'w1d2',
            'text' => 'Bench 5x5',
            'device_id' => 'dev-1',
        ]);
        PrismProgramCell::where('id', $cell2->id)->update(['updated_at' => $t]);

        $overlay = PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'back-squat',
            'record' => ['custom_notes' => 'Low bar'],
            'device_id' => 'dev-1',
            'deleted_at' => null,
        ]);
        PrismExerciseOverlayRecord::where('id', $overlay->id)->update(['updated_at' => $t]);

        $pref = PrismPreference::create([
            'gym_id' => 'the-cove',
            'data' => ['theme' => 'dark'],
            'device_id' => 'dev-1',
        ]);
        PrismPreference::where('id', $pref->id)->update(['updated_at' => $t]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'cursor', 'programs', 'cells', 'exercises', 'preferences']);

        $this->assertEquals('ok', $response->json('status'));
        $this->assertEquals($t->toIso8601String(), $response->json('cursor'));

        $programs = $response->json('programs');
        $this->assertCount(1, $programs);
        $this->assertEquals([
            'program_uuid' => 'prog-1',
            'name' => 'Strength Cycle',
            'week_count' => 4,
            'metadata' => ['author' => 'Coach'],
            'archived_at' => null,
            'updated_at' => $t->toIso8601String(),
        ], $programs[0]);

        $cells = $response->json('cells');
        $this->assertCount(2, $cells);

        $exercises = $response->json('exercises');
        $this->assertCount(1, $exercises);
        $this->assertEquals([
            'exercise_id' => 'back-squat',
            'record' => ['custom_notes' => 'Low bar'],
            'deleted_at' => null,
            'updated_at' => $t->toIso8601String(),
        ], $exercises[0]);

        $this->assertEquals([
            'data' => ['theme' => 'dark'],
            'updated_at' => $t->toIso8601String(),
        ], $response->json('preferences'));
    }

    public function test_since_filters_to_rows_at_or_after_cursor(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $tBefore = Carbon::parse('2026-09-01 10:00:00', $tz);
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $tAfter = Carbon::parse('2026-09-01 14:00:00', $tz);

        // Programs: 1 before, 1 after
        $progOld = PrismProgram::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-old',
            'name' => 'Old Program',
            'week_count' => 1,
            'metadata' => null,
        ]);
        PrismProgram::where('id', $progOld->id)->update(['updated_at' => $tBefore]);

        $progNew = PrismProgram::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-new',
            'name' => 'New Program',
            'week_count' => 2,
            'metadata' => null,
        ]);
        PrismProgram::where('id', $progNew->id)->update(['updated_at' => $tAfter]);

        // Cells: 1 before, 1 at since, 1 after
        $cellOld = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-old',
            'cell_id' => 'w1d1',
            'text' => 'Old cell',
        ]);
        PrismProgramCell::where('id', $cellOld->id)->update(['updated_at' => $tBefore]);

        $cellAt = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-new',
            'cell_id' => 'w1d1',
            'text' => 'Cell at since',
        ]);
        PrismProgramCell::where('id', $cellAt->id)->update(['updated_at' => $tSince]);

        $cellNew = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-new',
            'cell_id' => 'w1d2',
            'text' => 'Cell after since',
        ]);
        PrismProgramCell::where('id', $cellNew->id)->update(['updated_at' => $tAfter]);

        // Exercises: 1 before, 1 after
        $exOld = PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'bench-press',
            'record' => ['grip' => 'narrow'],
            'deleted_at' => null,
        ]);
        PrismExerciseOverlayRecord::where('id', $exOld->id)->update(['updated_at' => $tBefore]);

        $exNew = PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'deadlift',
            'record' => ['stance' => 'sumo'],
            'deleted_at' => null,
        ]);
        PrismExerciseOverlayRecord::where('id', $exNew->id)->update(['updated_at' => $tAfter]);

        // Preferences: stamped before since
        $pref = PrismPreference::create([
            'gym_id' => 'the-cove',
            'data' => ['theme' => 'light'],
        ]);
        PrismPreference::where('id', $pref->id)->update(['updated_at' => $tBefore]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));

        $response->assertStatus(200);

        $programs = $response->json('programs');
        $this->assertCount(1, $programs);
        $this->assertEquals('prog-new', $programs[0]['program_uuid']);

        $cells = $response->json('cells');
        $this->assertCount(2, $cells);
        $cellIds = array_column($cells, 'cell_id');
        $this->assertContains('w1d1', $cellIds);
        $this->assertContains('w1d2', $cellIds);

        $exercises = $response->json('exercises');
        $this->assertCount(1, $exercises);
        $this->assertEquals('deadlift', $exercises[0]['exercise_id']);

        $this->assertNull($response->json('preferences'));
    }

    public function test_inclusive_boundary_delivers_two_writes_at_same_instant(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $t = Carbon::parse('2026-09-01T12:00:00.000001Z');

        $cell1 = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-sync',
            'cell_id' => 'w1d1',
            'text' => 'Cell 1 content',
        ]);
        PrismProgramCell::where('id', $cell1->id)->update(['updated_at' => $t]);

        $cell2 = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-sync',
            'cell_id' => 'w1d2',
            'text' => 'Cell 2 content',
        ]);
        PrismProgramCell::where('id', $cell2->id)->update(['updated_at' => $t]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($t->toIso8601String()));

        $response->assertStatus(200);

        $cells = $response->json('cells');
        $this->assertCount(2, $cells);

        $cellIds = array_column($cells, 'cell_id');
        $this->assertContains('w1d1', $cellIds);
        $this->assertContains('w1d2', $cellIds);
    }

    public function test_tombstone_is_delivered_in_delta(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $tTombstone = Carbon::parse('2026-09-01 14:00:00', $tz);

        $record = PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'removed-lift',
            'record' => null,
            'device_id' => 'dev-tombstone',
            'deleted_at' => $tTombstone,
        ]);
        PrismExerciseOverlayRecord::where('id', $record->id)->update([
            'updated_at' => $tTombstone,
            'deleted_at' => $tTombstone,
        ]);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));

        $response->assertStatus(200);

        $exercises = $response->json('exercises');
        $this->assertCount(1, $exercises);
        $this->assertEquals('removed-lift', $exercises[0]['exercise_id']);
        $this->assertNull($exercises[0]['record']);
        $this->assertEquals($tTombstone->toIso8601String(), $exercises[0]['deleted_at']);
        $this->assertEquals($tTombstone->toIso8601String(), $exercises[0]['updated_at']);
    }

    public function test_cursor_is_non_rewinding_high_water_mark(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t1 = Carbon::parse('2026-09-01 10:00:00', $tz);
        $t2 = Carbon::parse('2026-09-01 15:00:00', $tz);

        $cell = PrismProgramCell::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-1',
            'cell_id' => 'w1d1',
            'text' => 'Content',
        ]);
        PrismProgramCell::where('id', $cell->id)->update(['updated_at' => $t1]);

        $overlay = PrismExerciseOverlayRecord::create([
            'gym_id' => 'the-cove',
            'exercise_id' => 'deadlift',
            'record' => null,
            'deleted_at' => $t2,
        ]);
        PrismExerciseOverlayRecord::where('id', $overlay->id)->update([
            'updated_at' => $t1,
            'deleted_at' => $t2,
        ]);

        // Cold start: cursor = max(updated_at, deleted_at) = t2
        $response1 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $response1->assertStatus(200);
        $this->assertEquals($t2->toIso8601String(), $response1->json('cursor'));

        // Query with since later than all rows: cursor stays at since (never rewinds)
        $tLater = Carbon::parse('2026-09-01 18:00:00', $tz);
        $response2 = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tLater->toIso8601String()));
        $response2->assertStatus(200);
        $this->assertEquals($tLater->toIso8601String(), $response2->json('cursor'));
    }

    public function test_empty_gym_returns_empty_collections(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Cold start with empty gym
        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'programs' => [],
                'cells' => [],
                'exercises' => [],
                'preferences' => null,
            ]);
        $this->assertNotNull($response->json('cursor'));

        // With since on empty gym
        $tz = config('app.timezone');
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $responseSince = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));

        $responseSince->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'cursor' => $tSince->toIso8601String(),
                'programs' => [],
                'cells' => [],
                'exercises' => [],
                'preferences' => null,
            ]);
    }

    public function test_unknown_gym_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/nonexistent/changes');

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Gym not found.',
            ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_invalid_since_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=not-a-date');

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function test_changes_query_count_is_flat_under_growing_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 1. Seed small dataset: 1 program, 2 cells, 2 exercise overlays, 1 preferences row
        PrismProgram::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-small-1',
            'name' => 'Small Program',
            'week_count' => 1,
            'metadata' => ['note' => 'small'],
            'device_id' => 'dev-1',
        ]);

        for ($i = 1; $i <= 2; $i++) {
            PrismProgramCell::create([
                'gym_id' => 'the-cove',
                'program_uuid' => 'prog-small-1',
                'cell_id' => "w1d{$i}",
                'text' => "Exercise {$i}",
                'device_id' => 'dev-1',
            ]);
        }

        for ($i = 1; $i <= 2; $i++) {
            PrismExerciseOverlayRecord::create([
                'gym_id' => 'the-cove',
                'exercise_id' => "exercise-small-{$i}",
                'record' => ['name' => "Custom {$i}"],
                'device_id' => 'dev-1',
                'deleted_at' => null,
            ]);
        }

        PrismPreference::create([
            'gym_id' => 'the-cove',
            'data' => ['color' => 'blue'],
            'device_id' => 'dev-1',
        ]);

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $responseSmall = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $smallCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $responseSmall->assertStatus(200);

        // 2. Seed large dataset into the same gym: 10 programs, 50 cells, 30 overlays
        for ($p = 1; $p <= 10; $p++) {
            PrismProgram::create([
                'gym_id' => 'the-cove',
                'program_uuid' => "prog-large-{$p}",
                'name' => "Large Program {$p}",
                'week_count' => 5,
                'metadata' => ['note' => "large-{$p}"],
                'device_id' => 'dev-1',
            ]);
        }

        for ($c = 1; $c <= 50; $c++) {
            $progIndex = ($c % 10) + 1;
            PrismProgramCell::create([
                'gym_id' => 'the-cove',
                'program_uuid' => "prog-large-{$progIndex}",
                'cell_id' => "w1d{$c}",
                'text' => "Cell text {$c}",
                'device_id' => 'dev-1',
            ]);
        }

        for ($e = 1; $e <= 30; $e++) {
            PrismExerciseOverlayRecord::create([
                'gym_id' => 'the-cove',
                'exercise_id' => "exercise-large-{$e}",
                'record' => ['name' => "Custom Overlay {$e}"],
                'device_id' => 'dev-1',
                'deleted_at' => ($e % 5 === 0) ? now() : null,
            ]);
        }

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $responseLarge = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $largeCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $responseLarge->assertStatus(200);

        // 3. Assert counts are equal and small (<= 12)
        $this->assertSame($smallCount, $largeCount);
        $this->assertLessThanOrEqual(12, $smallCount);
    }

    public function test_changes_programs_carry_archived_at_and_archive_appears_in_since_delta(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tz = config('app.timezone');
        $t1 = Carbon::parse('2026-09-01 10:00:00', $tz);
        $tSince = Carbon::parse('2026-09-01 12:00:00', $tz);
        $tArchive = Carbon::parse('2026-09-01 14:00:00', $tz);

        $program = PrismProgram::create([
            'gym_id' => 'the-cove',
            'program_uuid' => 'prog-archived',
            'name' => 'To Be Archived',
            'week_count' => 4,
            'metadata' => null,
            'archived_at' => null,
        ]);
        PrismProgram::where('id', $program->id)->update(['updated_at' => $t1]);

        // Cold start shows program with archived_at = null
        $responseCold = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes');
        $responseCold->assertStatus(200);
        $this->assertCount(1, $responseCold->json('programs'));
        $this->assertNull($responseCold->json('programs.0.archived_at'));

        // Query with since: program updated at t1 is excluded
        $responseSinceBefore = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));
        $responseSinceBefore->assertStatus(200);
        $this->assertCount(0, $responseSinceBefore->json('programs'));

        // Archive the program (simulating upsert action or update) bumping updated_at to tArchive
        $program->update([
            'archived_at' => $tArchive,
        ]);
        PrismProgram::where('id', $program->id)->update(['updated_at' => $tArchive]);

        // Now query with since: program appears in delta with ISO-8601 archived_at
        $responseSinceAfter = $this->withHeaders(['X-Device-Id' => 'dev-1'])
            ->getJson('/api/sync/prism/the-cove/changes?since=' . urlencode($tSince->toIso8601String()));
        $responseSinceAfter->assertStatus(200);
        $programs = $responseSinceAfter->json('programs');
        $this->assertCount(1, $programs);
        $this->assertSame('prog-archived', $programs[0]['program_uuid']);
        $this->assertSame($tArchive->toIso8601String(), $programs[0]['archived_at']);
        $this->assertSame($tArchive->toIso8601String(), $programs[0]['updated_at']);
    }
}
