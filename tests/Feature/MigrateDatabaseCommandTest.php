<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class MigrateDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $originalDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original default connection
        $this->originalDefaultConnection = Config::get('database.default');
        
        // Configure test MySQL connection if available
        Config::set('database.connections.test_mysql', [
            'driver' => 'mysql',
            'host' => env('TEST_MYSQL_HOST', '127.0.0.1'),
            'port' => env('TEST_MYSQL_PORT', '3306'),
            'database' => env('TEST_MYSQL_DATABASE', 'test_migration'),
            'username' => env('TEST_MYSQL_USERNAME', 'root'),
            'password' => env('TEST_MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
        
        // Configure test SQLite connections
        Config::set('database.connections.test_sqlite_source', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        Config::set('database.connections.test_sqlite_target', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function tearDown(): void
    {
        // Restore original default connection
        if ($this->originalDefaultConnection) {
            Config::set('database.default', $this->originalDefaultConnection);
        }
        
        // Clean up test MySQL database if it exists
        try {
            if ($this->canConnectToMySQL()) {
                $tables = DB::connection('test_mysql')->select('SHOW TABLES');
                DB::connection('test_mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                foreach ($tables as $table) {
                    $tableName = array_values((array)$table)[0];
                    DB::connection('test_mysql')->statement("DROP TABLE IF EXISTS `{$tableName}`");
                }
                DB::connection('test_mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        parent::tearDown();
    }

    protected function canConnectToMySQL(): bool
    {
        try {
            DB::connection('test_mysql')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createSampleSchema(string $connection): void
    {
        // Create users table
        Schema::connection($connection)->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
        
        // Create exercises table
        Schema::connection($connection)->create('exercises', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Create lift_logs table
        Schema::connection($connection)->create('lift_logs', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained('exercises')->onDelete('cascade');
            $table->integer('weight')->nullable();
            $table->integer('reps')->nullable();
            $table->date('logged_at');
            $table->timestamps();
        });
    }

    protected function insertSampleData(string $connection): array
    {
        // Insert users
        $userId1 = DB::connection($connection)->table('users')->insertGetId([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $userId2 = DB::connection($connection)->table('users')->insertGetId([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Insert exercises
        $exerciseId1 = DB::connection($connection)->table('exercises')->insertGetId([
            'user_id' => $userId1,
            'title' => 'Bench Press',
            'description' => 'Chest exercise',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $exerciseId2 = DB::connection($connection)->table('exercises')->insertGetId([
            'user_id' => null,
            'title' => 'Squat',
            'description' => 'Leg exercise',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Insert lift logs
        DB::connection($connection)->table('lift_logs')->insert([
            [
                'user_id' => $userId1,
                'exercise_id' => $exerciseId1,
                'weight' => 100,
                'reps' => 10,
                'logged_at' => '2024-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId1,
                'exercise_id' => $exerciseId2,
                'weight' => 150,
                'reps' => 5,
                'logged_at' => '2024-01-02',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId2,
                'exercise_id' => $exerciseId2,
                'weight' => 120,
                'reps' => 8,
                'logged_at' => '2024-01-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        return [
            'users' => 2,
            'exercises' => 2,
            'lift_logs' => 3,
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_data_from_sqlite_to_sqlite_with_sample_data()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data in source
        $expectedCounts = $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify data was migrated
        $this->assertEquals(
            $expectedCounts['users'],
            DB::connection('test_sqlite_target')->table('users')->count()
        );
        $this->assertEquals(
            $expectedCounts['exercises'],
            DB::connection('test_sqlite_target')->table('exercises')->count()
        );
        $this->assertEquals(
            $expectedCounts['lift_logs'],
            DB::connection('test_sqlite_target')->table('lift_logs')->count()
        );
        
        // Verify specific data
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], 'test_sqlite_target');
        
        $this->assertDatabaseHas('exercises', [
            'title' => 'Bench Press',
            'description' => 'Chest exercise',
        ], 'test_sqlite_target');
        
        $this->assertDatabaseHas('lift_logs', [
            'weight' => 100,
            'reps' => 10,
        ], 'test_sqlite_target');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_data_from_sqlite_to_mysql_with_sample_data()
    {
        if (!$this->canConnectToMySQL()) {
            $this->markTestSkipped('MySQL connection not available');
        }
        
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_mysql');
        
        // Insert sample data in source
        $expectedCounts = $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_mysql',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify data was migrated
        $this->assertEquals(
            $expectedCounts['users'],
            DB::connection('test_mysql')->table('users')->count()
        );
        $this->assertEquals(
            $expectedCounts['exercises'],
            DB::connection('test_mysql')->table('exercises')->count()
        );
        $this->assertEquals(
            $expectedCounts['lift_logs'],
            DB::connection('test_mysql')->table('lift_logs')->count()
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_data_from_mysql_to_sqlite_with_sample_data()
    {
        if (!$this->canConnectToMySQL()) {
            $this->markTestSkipped('MySQL connection not available');
        }
        
        // Create schema in both connections
        $this->createSampleSchema('test_mysql');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data in source
        $expectedCounts = $this->insertSampleData('test_mysql');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_mysql',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify data was migrated
        $this->assertEquals(
            $expectedCounts['users'],
            DB::connection('test_sqlite_target')->table('users')->count()
        );
        $this->assertEquals(
            $expectedCounts['exercises'],
            DB::connection('test_sqlite_target')->table('exercises')->count()
        );
        $this->assertEquals(
            $expectedCounts['lift_logs'],
            DB::connection('test_sqlite_target')->table('lift_logs')->count()
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_truncates_tables_with_fresh_option()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data in both
        $this->insertSampleData('test_sqlite_source');
        $this->insertSampleData('test_sqlite_target');
        
        // Verify target has data before migration
        $this->assertGreaterThan(0, DB::connection('test_sqlite_target')->table('users')->count());
        
        // Run migration with --fresh option (using --no-interaction to skip confirmation)
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
            '--fresh' => true,
            '--no-interaction' => true,
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify data was replaced (not duplicated)
        $this->assertEquals(2, DB::connection('test_sqlite_target')->table('users')->count());
        $this->assertEquals(2, DB::connection('test_sqlite_target')->table('exercises')->count());
        $this->assertEquals(3, DB::connection('test_sqlite_target')->table('lift_logs')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_duplicate_records_without_fresh_option()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert same data in both
        $this->insertSampleData('test_sqlite_source');
        $this->insertSampleData('test_sqlite_target');
        
        // Run migration without --fresh option
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify no duplicates were created
        $this->assertEquals(2, DB::connection('test_sqlite_target')->table('users')->count());
        
        // Verify output mentions skipped records
        $output = Artisan::output();
        $this->assertStringContainsString('skipped', strtolower($output));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_runs_in_dry_run_mode_without_making_changes()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data in source only
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration in dry-run mode
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
            '--dry-run' => true,
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify no data was migrated
        $this->assertEquals(0, DB::connection('test_sqlite_target')->table('users')->count());
        $this->assertEquals(0, DB::connection('test_sqlite_target')->table('exercises')->count());
        $this->assertEquals(0, DB::connection('test_sqlite_target')->table('lift_logs')->count());
        
        // Verify output indicates dry-run mode
        $output = Artisan::output();
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_progress_information_during_migration()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify output contains progress information
        $output = Artisan::output();
        $this->assertStringContainsString('Migrating', $output);
        $this->assertStringContainsString('users', $output);
        $this->assertStringContainsString('exercises', $output);
        $this->assertStringContainsString('lift_logs', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_summary_after_migration()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify output contains summary
        $output = Artisan::output();
        $this->assertStringContainsString('Migration Summary', $output);
        $this->assertStringContainsString('Total records', $output);
        $this->assertStringContainsString('Duration', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_invalid_source_connection()
    {
        // Create schema in target only
        $this->createSampleSchema('test_sqlite_target');
        
        // Run migration with invalid source
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'nonexistent_connection',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(1, $exitCode);
        
        // Verify error message
        $output = Artisan::output();
        $this->assertStringContainsString('connection', strtolower($output));
        $this->assertStringContainsString('does not exist', strtolower($output));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_invalid_target_connection()
    {
        // Create schema in source only
        $this->createSampleSchema('test_sqlite_source');
        
        // Run migration with invalid target
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'nonexistent_connection',
        ]);
        
        $this->assertEquals(1, $exitCode);
        
        // Verify error message
        $output = Artisan::output();
        $this->assertStringContainsString('connection', strtolower($output));
        $this->assertStringContainsString('does not exist', strtolower($output));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_lists_available_connections_on_invalid_connection()
    {
        // Run migration with invalid connection
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'nonexistent_connection',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(1, $exitCode);
        
        // Verify output lists available connections
        $output = Artisan::output();
        $this->assertStringContainsString('Available connections', $output);
        $this->assertStringContainsString('sqlite', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_target_connection_to_be_specified()
    {
        // Run migration without target
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
        ]);
        
        $this->assertEquals(1, $exitCode);
        
        // Verify error message
        $output = Artisan::output();
        $this->assertStringContainsString('--to', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_default_connection_when_from_not_specified()
    {
        // Use test_sqlite_source as the default for this test
        Config::set('database.default', 'test_sqlite_source');
        
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data in source (which is now the default)
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration without --from option (should use default)
        $exitCode = Artisan::call('db:migrate-data', [
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify data was migrated from default connection
        $this->assertGreaterThan(0, DB::connection('test_sqlite_target')->table('users')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_database_drivers_before_migration()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify output shows drivers
        $output = Artisan::output();
        $this->assertStringContainsString('sqlite', strtolower($output));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_tables_gracefully()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Don't insert any data
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify no errors occurred
        $this->assertEquals(0, DB::connection('test_sqlite_target')->table('users')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_foreign_key_relationships()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify relationships are intact
        $user = DB::connection('test_sqlite_target')->table('users')->first();
        $exercise = DB::connection('test_sqlite_target')
            ->table('exercises')
            ->where('user_id', $user->id)
            ->first();
        
        $this->assertNotNull($exercise);
        $this->assertEquals($user->id, $exercise->user_id);
        
        $liftLog = DB::connection('test_sqlite_target')
            ->table('lift_logs')
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->first();
        
        $this->assertNotNull($liftLog);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_tables_in_correct_dependency_order()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify output shows correct order (users before exercises before lift_logs)
        $output = Artisan::output();
        $usersPos = strpos($output, 'users');
        $exercisesPos = strpos($output, 'exercises');
        $liftLogsPos = strpos($output, 'lift_logs');
        
        $this->assertLessThan($exercisesPos, $usersPos);
        $this->assertLessThan($liftLogsPos, $exercisesPos);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_large_datasets_with_chunking()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert large dataset (more than default chunk size)
        $users = [];
        for ($i = 1; $i <= 150; $i++) {
            $users[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::connection('test_sqlite_source')->table('users')->insert($users);
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify all records were migrated
        $this->assertEquals(150, DB::connection('test_sqlite_target')->table('users')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_displays_verbose_output_when_verbose_flag_is_set()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert sample data
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration with verbose flag
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
            '--verbose' => true,
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify verbose output contains detailed information
        $output = Artisan::output();
        $this->assertStringContainsString('Total records', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_null_values_correctly()
    {
        // Create schema in both connections
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Insert data with null values
        DB::connection('test_sqlite_source')->table('users')->insert([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $userId = DB::connection('test_sqlite_source')->table('users')->first()->id;
        
        DB::connection('test_sqlite_source')->table('exercises')->insert([
            'user_id' => null, // Null foreign key
            'title' => 'Global Exercise',
            'description' => null, // Null text field
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify null values were preserved
        $exercise = DB::connection('test_sqlite_target')->table('exercises')->first();
        $this->assertNull($exercise->user_id);
        $this->assertNull($exercise->description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rolls_back_on_error_during_migration()
    {
        // Create schema in source
        $this->createSampleSchema('test_sqlite_source');
        
        // Create incomplete schema in target (missing lift_logs table to cause error)
        Schema::connection('test_sqlite_target')->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
        
        Schema::connection('test_sqlite_target')->create('exercises', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Insert sample data in source
        $this->insertSampleData('test_sqlite_source');
        
        // Run migration (should fail on lift_logs table)
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(1, $exitCode);
        
        // Verify error message
        $output = Artisan::output();
        $this->assertStringContainsString('error', strtolower($output));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_excludes_laravel_system_tables()
    {
        // Create schema with system tables
        $this->createSampleSchema('test_sqlite_source');
        $this->createSampleSchema('test_sqlite_target');
        
        // Create system tables
        Schema::connection('test_sqlite_source')->create('migrations', function ($table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
        });
        
        Schema::connection('test_sqlite_source')->create('cache', function ($table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');
        });
        
        // Insert data
        $this->insertSampleData('test_sqlite_source');
        DB::connection('test_sqlite_source')->table('migrations')->insert([
            'migration' => '2024_01_01_000000_test',
            'batch' => 1,
        ]);
        
        // Run migration
        $exitCode = Artisan::call('db:migrate-data', [
            '--from' => 'test_sqlite_source',
            '--to' => 'test_sqlite_target',
        ]);
        
        $this->assertEquals(0, $exitCode);
        
        // Verify system tables were not migrated
        $output = Artisan::output();
        $this->assertStringNotContainsString('migrations', strtolower($output));
        $this->assertStringNotContainsString('cache', strtolower($output));
    }
}
