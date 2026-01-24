<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN for ENUM, so we need to recreate the table
        // For other databases, we would use ALTER TABLE
        
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite approach: drop and recreate with new enum values
            // First drop the index that references pr_type
            Schema::table('personal_records', function (Blueprint $table) {
                $table->dropIndex('personal_records_user_id_exercise_id_pr_type_index');
            });
            
            // Drop the old pr_type column
            Schema::table('personal_records', function (Blueprint $table) {
                $table->dropColumn('pr_type');
            });
            
            // Add the new pr_type column with 'time' included
            Schema::table('personal_records', function (Blueprint $table) {
                $table->enum('pr_type', ['one_rm', 'volume', 'rep_specific', 'hypertrophy', 'time'])->after('lift_log_id');
            });
            
            // Recreate the index
            Schema::table('personal_records', function (Blueprint $table) {
                $table->index(['user_id', 'exercise_id', 'pr_type']);
            });
        } else {
            // MySQL/PostgreSQL approach
            DB::statement("ALTER TABLE personal_records MODIFY COLUMN pr_type ENUM('one_rm', 'volume', 'rep_specific', 'hypertrophy', 'time')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Drop the index
            Schema::table('personal_records', function (Blueprint $table) {
                $table->dropIndex('personal_records_user_id_exercise_id_pr_type_index');
            });
            
            // Drop the column
            Schema::table('personal_records', function (Blueprint $table) {
                $table->dropColumn('pr_type');
            });
            
            // Recreate with old enum values
            Schema::table('personal_records', function (Blueprint $table) {
                $table->enum('pr_type', ['one_rm', 'volume', 'rep_specific', 'hypertrophy'])->after('lift_log_id');
            });
            
            // Recreate the index
            Schema::table('personal_records', function (Blueprint $table) {
                $table->index(['user_id', 'exercise_id', 'pr_type']);
            });
        } else {
            DB::statement("ALTER TABLE personal_records MODIFY COLUMN pr_type ENUM('one_rm', 'volume', 'rep_specific', 'hypertrophy')");
        }
    }
};
