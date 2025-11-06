<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add exercise_type column as nullable initially
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type', 50)->nullable()->after('band_type');
            $table->index('exercise_type', 'idx_exercises_exercise_type');
        });

        // Step 2: Populate exercise_type field based on existing data
        $this->populateExerciseTypes();

        // Step 3: Make the field non-nullable after population
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('exercise_type', 50)->nullable(false)->change();
        });

        // Step 4: Validate migration success
        $this->validateMigration();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback is safe - just drop the new column and index
        // Legacy fields remain intact during transition
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex('idx_exercises_exercise_type');
            $table->dropColumn('exercise_type');
        });
    }

    /**
     * Populate exercise_type field based on existing is_bodyweight and band_type fields
     * Migration logic follows requirements 3.1, 3.2, 3.3, 3.4, 3.5
     */
    private function populateExerciseTypes(): void
    {
        // Priority order for type assignment:
        // 1. Banded resistance exercises (band_type = 'resistance')
        // 2. Banded assistance exercises (band_type = 'assistance') 
        // 3. Bodyweight exercises (is_bodyweight = true AND band_type IS NULL)
        // 4. Regular exercises (everything else)

        // Step 1: Banded resistance exercises
        $resistanceCount = DB::table('exercises')
            ->where('band_type', 'resistance')
            ->update(['exercise_type' => 'banded_resistance']);

        // Step 2: Banded assistance exercises  
        $assistanceCount = DB::table('exercises')
            ->where('band_type', 'assistance')
            ->update(['exercise_type' => 'banded_assistance']);

        // Step 3: Bodyweight exercises (only if not already assigned a banded type)
        $bodyweightCount = DB::table('exercises')
            ->where('is_bodyweight', true)
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'bodyweight']);

        // Step 4: Regular exercises (everything else that hasn't been assigned)
        $regularCount = DB::table('exercises')
            ->whereNull('exercise_type')
            ->update(['exercise_type' => 'regular']);

        // Log the migration results for verification
        $this->logMigrationResults($resistanceCount, $assistanceCount, $bodyweightCount, $regularCount);
    }

    /**
     * Validate that the migration completed successfully
     * Requirements: 6.1, 6.2, 6.3, 6.4, 6.5
     */
    private function validateMigration(): void
    {
        // Check for exercises that couldn't be categorized
        $uncategorized = DB::table('exercises')->whereNull('exercise_type')->count();
        if ($uncategorized > 0) {
            throw new \Exception("Migration incomplete: {$uncategorized} exercises still have NULL exercise_type");
        }

        // Validate type distribution and ensure only valid types exist
        $validTypes = ['regular', 'bodyweight', 'banded_resistance', 'banded_assistance'];
        $typeCounts = DB::table('exercises')
            ->select('exercise_type', DB::raw('COUNT(*) as count'))
            ->groupBy('exercise_type')
            ->get();

        $totalExercises = 0;
        foreach ($typeCounts as $typeCount) {
            $totalExercises += $typeCount->count;
            
            if (!in_array($typeCount->exercise_type, $validTypes)) {
                throw new \Exception("Migration error: Invalid exercise_type value '{$typeCount->exercise_type}' found");
            }
        }

        // Ensure we have at least some exercises (sanity check)
        if ($totalExercises === 0) {
            // This is not necessarily an error - the table might be empty
            echo "Warning: No exercises found in the table\n";
        }

        // Final validation: ensure no invalid exercise_type values exist
        $invalidTypes = DB::table('exercises')
            ->whereNotIn('exercise_type', $validTypes)
            ->count();
            
        if ($invalidTypes > 0) {
            throw new \Exception("Migration validation failed: {$invalidTypes} exercises have invalid exercise_type values");
        }

        echo "Migration validation successful: All exercises have valid exercise_type values\n";
    }

    /**
     * Log the results of the migration for verification
     */
    private function logMigrationResults(int $resistanceCount, int $assistanceCount, int $bodyweightCount, int $regularCount): void
    {
        echo "Exercise Type Migration Results:\n";
        echo "- Banded Resistance: {$resistanceCount} exercises\n";
        echo "- Banded Assistance: {$assistanceCount} exercises\n";
        echo "- Bodyweight: {$bodyweightCount} exercises\n";
        echo "- Regular: {$regularCount} exercises\n";
        echo "- Total: " . ($resistanceCount + $assistanceCount + $bodyweightCount + $regularCount) . " exercises migrated\n";
    }
};
