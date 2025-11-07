<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\MigrateDatabaseCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateDatabaseCommandTest extends TestCase
{

    private function getCommand()
    {
        return new MigrateDatabaseCommand();
    }

    private function callPrivateMethod($object, $method, $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    // ========================================================================
    // Connection Validation Tests
    // ========================================================================

    public function test_validate_connection_returns_true_for_existing_connection()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'validateConnection', ['sqlite']);
        
        $this->assertTrue($result);
    }

    public function test_validate_connection_returns_false_for_nonexistent_connection()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'validateConnection', ['nonexistent']);
        
        $this->assertFalse($result);
    }

    public function test_test_connection_returns_true_for_valid_connection()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'testConnection', ['sqlite']);
        
        $this->assertTrue($result);
    }

    public function test_get_connection_driver_returns_correct_driver()
    {
        $command = $this->getCommand();
        
        $driver = $this->callPrivateMethod($command, 'getConnectionDriver', ['sqlite']);
        
        $this->assertEquals('sqlite', $driver);
    }

    // ========================================================================
    // Schema Analysis and Table Discovery Tests
    // ========================================================================

    public function test_get_tables_returns_array()
    {
        // Create a simple test database
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE test_table (id INTEGER PRIMARY KEY)');
        DB::connection('test_db')->statement('CREATE TABLE migrations (id INTEGER PRIMARY KEY)');
        
        $command = $this->getCommand();
        $tables = $this->callPrivateMethod($command, 'getTables', ['test_db']);
        
        $this->assertIsArray($tables);
        $this->assertContains('test_table', $tables);
        $this->assertNotContains('migrations', $tables);
    }

    public function test_get_tables_excludes_laravel_system_tables()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        DB::connection('test_db')->statement('CREATE TABLE migrations (id INTEGER PRIMARY KEY)');
        DB::connection('test_db')->statement('CREATE TABLE cache (key TEXT PRIMARY KEY)');
        
        $command = $this->getCommand();
        $tables = $this->callPrivateMethod($command, 'getTables', ['test_db']);
        
        $systemTables = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];
        
        foreach ($systemTables as $systemTable) {
            $this->assertNotContains($systemTable, $tables);
        }
    }

    public function test_get_foreign_keys_returns_foreign_key_relationships()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        DB::connection('test_db')->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, FOREIGN KEY(user_id) REFERENCES users(id))');
        
        $command = $this->getCommand();
        $foreignKeys = $this->callPrivateMethod($command, 'getForeignKeys', ['test_db', 'posts']);
        
        $this->assertIsArray($foreignKeys);
        $this->assertNotEmpty($foreignKeys);
        $this->assertEquals('user_id', $foreignKeys[0]['column']);
        $this->assertEquals('users', $foreignKeys[0]['references_table']);
    }

    public function test_get_foreign_keys_returns_empty_array_for_table_without_foreign_keys()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        
        $command = $this->getCommand();
        $foreignKeys = $this->callPrivateMethod($command, 'getForeignKeys', ['test_db', 'users']);
        
        $this->assertIsArray($foreignKeys);
        $this->assertEmpty($foreignKeys);
    }

    // ========================================================================
    // Dependency Resolution and Topological Sort Tests
    // ========================================================================

    public function test_topological_sort_orders_tables_correctly()
    {
        $command = $this->getCommand();
        
        // Create a simple dependency graph
        // users -> exercises -> lift_logs
        $graph = [
            'users' => ['exercises'],
            'exercises' => ['lift_logs'],
            'lift_logs' => [],
        ];
        
        $inDegree = [
            'users' => 0,
            'exercises' => 1,
            'lift_logs' => 1,
        ];
        
        $sorted = $this->callPrivateMethod($command, 'topologicalSort', [$graph, $inDegree]);
        
        $this->assertIsArray($sorted);
        $this->assertEquals(['users', 'exercises', 'lift_logs'], $sorted);
    }

    public function test_topological_sort_handles_multiple_independent_tables()
    {
        $command = $this->getCommand();
        
        // Create a graph with independent tables
        $graph = [
            'users' => [],
            'roles' => [],
            'units' => [],
        ];
        
        $inDegree = [
            'users' => 0,
            'roles' => 0,
            'units' => 0,
        ];
        
        $sorted = $this->callPrivateMethod($command, 'topologicalSort', [$graph, $inDegree]);
        
        $this->assertIsArray($sorted);
        $this->assertCount(3, $sorted);
        $this->assertContains('users', $sorted);
        $this->assertContains('roles', $sorted);
        $this->assertContains('units', $sorted);
    }

    public function test_topological_sort_handles_complex_dependencies()
    {
        $command = $this->getCommand();
        
        // Create a more complex dependency graph
        $graph = [
            'users' => ['exercises', 'programs'],
            'exercises' => ['lift_logs'],
            'programs' => [],
            'lift_logs' => [],
        ];
        
        $inDegree = [
            'users' => 0,
            'exercises' => 1,
            'programs' => 1,
            'lift_logs' => 1,
        ];
        
        $sorted = $this->callPrivateMethod($command, 'topologicalSort', [$graph, $inDegree]);
        
        $this->assertIsArray($sorted);
        
        // Users must come before exercises and programs
        $usersIndex = array_search('users', $sorted);
        $exercisesIndex = array_search('exercises', $sorted);
        $programsIndex = array_search('programs', $sorted);
        $liftLogsIndex = array_search('lift_logs', $sorted);
        
        $this->assertLessThan($exercisesIndex, $usersIndex);
        $this->assertLessThan($programsIndex, $usersIndex);
        $this->assertLessThan($liftLogsIndex, $exercisesIndex);
    }

    // ========================================================================
    // Circular Dependency Detection Tests
    // ========================================================================

    public function test_detect_circular_dependencies_returns_empty_for_valid_graph()
    {
        $command = $this->getCommand();
        
        // Create a valid dependency graph
        $graph = [
            'users' => ['exercises'],
            'exercises' => ['lift_logs'],
            'lift_logs' => [],
        ];
        
        $inDegree = [
            'users' => 0,
            'exercises' => 1,
            'lift_logs' => 1,
        ];
        
        $circular = $this->callPrivateMethod($command, 'detectCircularDependencies', [$graph, $inDegree]);
        
        $this->assertIsArray($circular);
        $this->assertEmpty($circular);
    }

    public function test_detect_circular_dependencies_detects_simple_cycle()
    {
        $command = $this->getCommand();
        
        // Create a circular dependency: A -> B -> A
        $graph = [
            'table_a' => ['table_b'],
            'table_b' => ['table_a'],
        ];
        
        $inDegree = [
            'table_a' => 1,
            'table_b' => 1,
        ];
        
        $circular = $this->callPrivateMethod($command, 'detectCircularDependencies', [$graph, $inDegree]);
        
        $this->assertIsArray($circular);
        $this->assertNotEmpty($circular);
        $this->assertContains('table_a', $circular);
        $this->assertContains('table_b', $circular);
    }

    public function test_detect_circular_dependencies_detects_complex_cycle()
    {
        $command = $this->getCommand();
        
        // Create a circular dependency: A -> B -> C -> A
        $graph = [
            'table_a' => ['table_b'],
            'table_b' => ['table_c'],
            'table_c' => ['table_a'],
        ];
        
        $inDegree = [
            'table_a' => 1,
            'table_b' => 1,
            'table_c' => 1,
        ];
        
        $circular = $this->callPrivateMethod($command, 'detectCircularDependencies', [$graph, $inDegree]);
        
        $this->assertIsArray($circular);
        $this->assertNotEmpty($circular);
        $this->assertCount(3, $circular);
    }

    // ========================================================================
    // Data Chunking Logic Tests
    // ========================================================================

    public function test_migrate_table_processes_data_in_chunks()
    {
        // Create source and target databases
        Config::set('database.connections.test_source', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        Config::set('database.connections.test_target', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        // Create table in both databases
        DB::connection('test_source')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE)');
        DB::connection('test_target')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE)');
        
        // Insert test data
        for ($i = 1; $i <= 50; $i++) {
            DB::connection('test_source')->table('test_users')->insert([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com"
            ]);
        }
        
        // Mock the output for the command
        $symfonyOutput = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        $output = new \Illuminate\Console\OutputStyle(
            $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
            $symfonyOutput
        );
        
        $command = $this->getCommand();
        $command->setOutput($output);
        
        $result = $this->callPrivateMethod($command, 'migrateTable', [
            'test_users',
            'test_source',
            'test_target',
            ['fresh' => true, 'dry_run' => false, 'verbose' => false]
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('migrated', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertEquals(50, $result['migrated']);
        $this->assertEquals(0, $result['skipped']);
        
        // Verify data was actually migrated
        $targetCount = DB::connection('test_target')->table('test_users')->count();
        $this->assertEquals(50, $targetCount);
    }

    public function test_migrate_table_handles_empty_table()
    {
        Config::set('database.connections.test_source', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        Config::set('database.connections.test_target', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_source')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');
        DB::connection('test_target')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');
        
        // Mock the output for the command
        $symfonyOutput = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        $output = new \Illuminate\Console\OutputStyle(
            $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
            $symfonyOutput
        );
        
        $command = $this->getCommand();
        $command->setOutput($output);
        
        $result = $this->callPrivateMethod($command, 'migrateTable', [
            'test_users',
            'test_source',
            'test_target',
            ['fresh' => true, 'dry_run' => false, 'verbose' => false]
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals(0, $result['migrated']);
        $this->assertEquals(0, $result['skipped']);
    }

    // ========================================================================
    // Duplicate Handling Tests
    // ========================================================================

    public function test_insert_with_duplicate_handling_skips_duplicates()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE)');
        DB::connection('test_db')->table('test_users')->insert(['name' => 'Existing User', 'email' => 'test@example.com']);
        
        $command = $this->getCommand();
        $records = [
            ['name' => 'Duplicate User', 'email' => 'test@example.com']
        ];
        
        $result = $this->callPrivateMethod($command, 'insertWithDuplicateHandling', [
            'test_users',
            $records,
            'test_db',
            ['verbose' => false]
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('inserted', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertEquals(0, $result['inserted']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_insert_with_duplicate_handling_inserts_unique_records()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE)');
        
        $command = $this->getCommand();
        $records = [
            ['name' => 'New User 1', 'email' => 'newuser1@example.com'],
            ['name' => 'New User 2', 'email' => 'newuser2@example.com']
        ];
        
        $result = $this->callPrivateMethod($command, 'insertWithDuplicateHandling', [
            'test_users',
            $records,
            'test_db',
            ['verbose' => false]
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['inserted']);
        $this->assertEquals(0, $result['skipped']);
        
        // Verify records were inserted
        $count = DB::connection('test_db')->table('test_users')->count();
        $this->assertEquals(2, $count);
    }

    public function test_insert_with_duplicate_handling_handles_mixed_records()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE)');
        DB::connection('test_db')->table('test_users')->insert(['name' => 'Existing User', 'email' => 'existing@example.com']);
        
        $command = $this->getCommand();
        $records = [
            ['name' => 'New User', 'email' => 'newuser@example.com'],
            ['name' => 'Duplicate User', 'email' => 'existing@example.com']
        ];
        
        $result = $this->callPrivateMethod($command, 'insertWithDuplicateHandling', [
            'test_users',
            $records,
            'test_db',
            ['verbose' => false]
        ]);
        
        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_is_unique_constraint_violation_detects_sqlite_constraint()
    {
        Config::set('database.connections.test_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        DB::connection('test_db')->statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, email TEXT UNIQUE)');
        DB::connection('test_db')->table('test_users')->insert(['email' => 'test@example.com']);
        
        $command = $this->getCommand();
        
        try {
            DB::connection('test_db')->table('test_users')->insert(['email' => 'test@example.com']);
            $this->fail('Expected QueryException was not thrown');
        } catch (\Illuminate\Database\QueryException $e) {
            $result = $this->callPrivateMethod($command, 'isUniqueConstraintViolation', [$e]);
            $this->assertTrue($result);
        }
    }

    // ========================================================================
    // Foreign Key Management Tests
    // ========================================================================

    public function test_disable_foreign_key_checks_for_sqlite()
    {
        $command = $this->getCommand();
        
        $this->callPrivateMethod($command, 'disableForeignKeyChecks', ['sqlite']);
        
        // Verify foreign keys are disabled
        $result = DB::connection('sqlite')->select('PRAGMA foreign_keys');
        $this->assertEquals(0, $result[0]->foreign_keys);
    }

    public function test_enable_foreign_key_checks_for_sqlite()
    {
        $command = $this->getCommand();
        
        // First disable
        $this->callPrivateMethod($command, 'disableForeignKeyChecks', ['sqlite']);
        
        // Then enable
        $this->callPrivateMethod($command, 'enableForeignKeyChecks', ['sqlite']);
        
        // Verify foreign keys are enabled
        $result = DB::connection('sqlite')->select('PRAGMA foreign_keys');
        $this->assertEquals(1, $result[0]->foreign_keys);
    }

    // ========================================================================
    // Utility Method Tests
    // ========================================================================

    public function test_format_duration_formats_seconds_correctly()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'formatDuration', [45.5]);
        $this->assertEquals('45.5s', $result);
    }

    public function test_format_duration_formats_minutes_correctly()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'formatDuration', [125]);
        $this->assertEquals('2m 5s', $result);
    }

    public function test_format_duration_formats_hours_correctly()
    {
        $command = $this->getCommand();
        
        $result = $this->callPrivateMethod($command, 'formatDuration', [3725]);
        $this->assertEquals('1h 2m 5s', $result);
    }
}
