<?php

namespace Tests\Feature;

use App\Models\LiftLog;
use App\Models\LiftSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteLegacyPurpleBandColorsMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function migration_soft_deletes_purple_rows_case_insensitively_and_leaves_other_rows()
    {
        $liftLog = LiftLog::factory()->create();

        // Seed with purple, Purple, and green rows
        $purpleSet1 = LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'purple',
        ]);

        $purpleSet2 = LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'purple',
        ]);
        \Illuminate\Support\Facades\DB::table('lift_sets')
            ->where('id', $purpleSet2->id)
            ->update(['band_color' => 'Purple']);
        $this->assertEquals('Purple', \Illuminate\Support\Facades\DB::table('lift_sets')->where('id', $purpleSet2->id)->value('band_color'));

        $greenSet = LiftSet::factory()->create([
            'lift_log_id' => $liftLog->id,
            'band_color' => 'green',
        ]);

        // Execute migration up() logic
        $migration = include database_path('migrations/2026_09_23_171453_delete_legacy_purple_band_colors_from_lift_sets.php');
        $migration->up();

        // Assert purple rows are soft-deleted (excluded from default scope, deleted_at is set)
        $this->assertSoftDeleted('lift_sets', ['id' => $purpleSet1->id]);
        $this->assertSoftDeleted('lift_sets', ['id' => $purpleSet2->id]);

        // Assert green row is untouched
        $this->assertDatabaseHas('lift_sets', [
            'id' => $greenSet->id,
            'deleted_at' => null,
            'band_color' => 'green',
        ]);
    }
}
