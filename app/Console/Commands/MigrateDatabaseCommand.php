<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use PDOException;
use Exception;

/**
 * Database Migration Command
 * 
 * This command migrates all data from one database connection to another while
 * preserving referential integrity by respecting foreign key relationships.
 * 
 * ============================================================================
 * USAGE MANUAL
 * ============================================================================
 * 
 * BASIC USAGE:
 * ------------
 * php artisan db:migrate-data --to=target_connection
 * 
 * This will migrate all data from your default database connection to the
 * specified target connection.
 * 
 * 
 * COMMAND OPTIONS:
 * ----------------
 * 
 * --from=CONNECTION_NAME
 *   Specify the source database connection name.
 *   If omitted, uses the default connection from config/database.php
 *   Example: php artisan db:migrate-data --from=sqlite --to=mysql
 * 
 * --to=CONNECTION_NAME (REQUIRED)
 *   Specify the target database connection name.
 *   This option is required.
 *   Example: php artisan db:migrate-data --to=mysql
 * 
 * --fresh
 *   Truncate all tables in the target database before migrating.
 *   WARNING: This will delete all existing data in the target database!
 *   You will be prompted for confirmation before proceeding.
 *   Example: php artisan db:migrate-data --to=mysql --fresh
 * 
 * --dry-run
 *   Preview the migration without making any changes.
 *   Useful for testing and validation before running the actual migration.
 *   Example: php artisan db:migrate-data --to=mysql --dry-run
 * 
 * -v, --verbose
 *   Display detailed information during migration including:
 *   - Record counts per table
 *   - Sample records in dry-run mode
 *   - Skipped duplicate records
 *   Example: php artisan db:migrate-data --to=mysql -v
 * 
 * 
 * COMMON SCENARIOS:
 * -----------------
 * 
 * 1. Migrate from SQLite to MySQL:
 *    php artisan db:migrate-data --from=sqlite --to=mysql
 * 
 * 2. Fresh migration (clean target database first):
 *    php artisan db:migrate-data --to=mysql --fresh
 * 
 * 3. Preview migration without making changes:
 *    php artisan db:migrate-data --to=mysql --dry-run
 * 
 * 4. Verbose migration with detailed output:
 *    php artisan db:migrate-data --to=mysql -v
 * 
 * 5. Migrate from default connection to a new database:
 *    php artisan db:migrate-data --to=production
 * 
 * 
 * SETUP REQUIREMENTS:
 * -------------------
 * 
 * 1. Configure database connections in config/database.php or .env file:
 * 
 *    For MySQL:
 *    DB_CONNECTION=mysql
 *    DB_HOST=127.0.0.1
 *    DB_PORT=3306
 *    DB_DATABASE=your_database
 *    DB_USERNAME=your_username
 *    DB_PASSWORD=your_password
 * 
 *    For SQLite:
 *    DB_CONNECTION=sqlite
 *    DB_DATABASE=/absolute/path/to/database.sqlite
 * 
 * 2. Add additional connections in config/database.php:
 * 
 *    'connections' => [
 *        'mysql' => [...],
 *        'sqlite' => [...],
 *        'production' => [
 *            'driver' => 'mysql',
 *            'host' => 'production-server.com',
 *            'database' => 'prod_db',
 *            ...
 *        ],
 *    ]
 * 
 * 3. Ensure both source and target databases are accessible and running.
 * 
 * 
 * HOW IT WORKS:
 * -------------
 * 
 * 1. Connection Validation:
 *    - Verifies both source and target connections exist in configuration
 *    - Tests actual connectivity to both databases
 *    - Displays connection information (driver type)
 * 
 * 2. Schema Analysis:
 *    - Discovers all tables in the source database
 *    - Excludes Laravel system tables (migrations, cache, sessions, jobs, etc.)
 *    - Analyzes foreign key relationships between tables
 * 
 * 3. Dependency Resolution:
 *    - Uses topological sort to determine correct migration order
 *    - Ensures parent tables are migrated before child tables
 *    - Detects and reports circular dependencies
 * 
 * 4. Data Migration:
 *    - Disables foreign key checks during migration
 *    - Migrates tables in dependency order
 *    - Processes data in chunks (1000 records at a time)
 *    - Handles duplicate records gracefully (skips on unique constraint violations)
 *    - Wraps each table migration in a transaction for safety
 *    - Re-enables foreign key checks after completion
 * 
 * 5. Progress Tracking:
 *    - Displays progress bar for each table
 *    - Shows record counts and migration status
 *    - Provides detailed summary at the end
 * 
 * 
 * DUPLICATE HANDLING:
 * -------------------
 * 
 * When NOT using --fresh option:
 * - Duplicate records (based on unique constraints) are automatically skipped
 * - Skipped records are counted and reported in the summary
 * - Use -v flag to see which specific records were skipped
 * 
 * When using --fresh option:
 * - Target tables are truncated before migration
 * - No duplicate checking is performed (faster migration)
 * - All records are inserted as new
 * 
 * 
 * ERROR HANDLING:
 * ---------------
 * 
 * The command handles various error scenarios:
 * 
 * - Connection failures: Displays helpful troubleshooting tips
 * - Circular dependencies: Reports which tables are involved
 * - Constraint violations: Skips duplicates, reports other violations
 * - Transaction failures: Rolls back changes for the current table
 * - Foreign key issues: Automatically manages FK checks
 * 
 * If migration fails:
 * - Foreign key checks are re-enabled
 * - Partial data may exist in target database
 * - Use --fresh option to retry with clean slate
 * 
 * 
 * PERFORMANCE TIPS:
 * -----------------
 * 
 * - Use --fresh for faster migration when target database is empty
 * - Chunk size is set to 1000 records (optimal for most cases)
 * - Large databases may take significant time
 * - Consider running during off-peak hours for production migrations
 * - Use --dry-run first to estimate migration time
 * 
 * 
 * SAFETY FEATURES:
 * ----------------
 * 
 * - Confirmation prompt when using --fresh option
 * - Dry-run mode for testing without changes
 * - Transaction wrapping for each table
 * - Automatic foreign key constraint management
 * - Detailed error messages with troubleshooting guidance
 * - Progress tracking to monitor migration status
 * 
 * 
 * SUPPORTED DATABASES:
 * --------------------
 * 
 * Currently supports:
 * - MySQL / MariaDB
 * - SQLite
 * 
 * You can migrate between any combination of supported databases.
 * 
 * 
 * EXAMPLES:
 * ---------
 * 
 * # Preview migration from SQLite to MySQL
 * php artisan db:migrate-data --from=sqlite --to=mysql --dry-run -v
 * 
 * # Perform actual migration
 * php artisan db:migrate-data --from=sqlite --to=mysql
 * 
 * # Fresh migration with verbose output
 * php artisan db:migrate-data --to=mysql --fresh -v
 * 
 * # Migrate to production database
 * php artisan db:migrate-data --to=production
 * 
 * ============================================================================
 */
class MigrateDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:migrate-data 
        {--from= : Source database connection name (defaults to default connection)}
        {--to= : Target database connection name (required)}
        {--fresh : Truncate all tables in target database before migrating}
        {--dry-run : Preview migration without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all database data from one connection to another';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get source and target connections
        $sourceConnection = $this->option('from') ?: Config::get('database.default');
        $targetConnection = $this->option('to');

        // Validate target connection is specified
        if (!$targetConnection) {
            $this->error('Target connection (--to) is required.');
            $this->displayAvailableConnections();
            return Command::FAILURE;
        }

        // Validate connections exist
        if (!$this->validateConnection($sourceConnection)) {
            $this->error("Source connection '{$sourceConnection}' does not exist.");
            $this->displayAvailableConnections();
            return Command::FAILURE;
        }

        if (!$this->validateConnection($targetConnection)) {
            $this->error("Target connection '{$targetConnection}' does not exist.");
            $this->displayAvailableConnections();
            return Command::FAILURE;
        }

        // Test actual connectivity with detailed error messages
        $sourceError = $this->testConnectionWithError($sourceConnection);
        if ($sourceError !== null) {
            $this->error("Unable to connect to source database '{$sourceConnection}'.");
            $this->error("Error: {$sourceError}");
            $this->newLine();
            $this->line('Common connection issues:');
            $this->line('  • Check database credentials in .env file');
            $this->line('  • Verify database server is running');
            $this->line('  • Ensure database file exists (for SQLite)');
            $this->line('  • Check network connectivity (for remote databases)');
            return Command::FAILURE;
        }

        $targetError = $this->testConnectionWithError($targetConnection);
        if ($targetError !== null) {
            $this->error("Unable to connect to target database '{$targetConnection}'.");
            $this->error("Error: {$targetError}");
            $this->newLine();
            $this->line('Common connection issues:');
            $this->line('  • Check database credentials in .env file');
            $this->line('  • Verify database server is running');
            $this->line('  • Ensure database file exists (for SQLite)');
            $this->line('  • Check network connectivity (for remote databases)');
            return Command::FAILURE;
        }

        // Display connection information
        $sourceDriver = $this->getConnectionDriver($sourceConnection);
        $targetDriver = $this->getConnectionDriver($targetConnection);
        
        $this->info("Source: {$sourceConnection} ({$sourceDriver})");
        $this->info("Target: {$targetConnection} ({$targetDriver})");
        
        if ($this->option('dry-run')) {
            $this->warn('Running in DRY-RUN mode - no data will be written');
        }

        // Confirm fresh migration if requested
        if ($this->option('fresh')) {
            if (!$this->confirmFreshMigration($targetConnection, $targetDriver)) {
                $this->info('Migration cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('Migration setup validated successfully.');
        
        try {
            // Get tables in dependency order
            $tables = $this->resolveDependencies($sourceConnection);
            
            if (empty($tables)) {
                $this->info('No tables to migrate.');
                return Command::SUCCESS;
            }
            
            $this->info('Tables to migrate: ' . count($tables));
            
            // Migrate tables
            $stats = $this->migrateTables($tables, $sourceConnection, $targetConnection, [
                'fresh' => $this->option('fresh'),
                'dry_run' => $this->option('dry-run'),
                'verbose' => $this->option('verbose'),
            ]);
            
            $this->info('Migration completed successfully.');
            $this->displaySummary($stats);
            
        } catch (Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    /**
     * Get all table names from a connection, excluding Laravel system tables.
     *
     * @param string $connectionName
     * @return array
     */
    protected function getTables(string $connectionName): array
    {
        $driver = $this->getConnectionDriver($connectionName);
        $connection = DB::connection($connectionName);
        
        $tables = [];
        
        if ($driver === 'sqlite') {
            $results = $connection->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            );
            $tables = array_map(fn($result) => $result->name, $results);
        } elseif ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $results = $connection->select("SHOW TABLES");
            $key = "Tables_in_{$database}";
            $tables = array_map(fn($result) => $result->$key, $results);
        }
        
        // Exclude Laravel system tables
        $systemTables = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];
        $tables = array_filter($tables, fn($table) => !in_array($table, $systemTables));
        
        return array_values($tables);
    }

    /**
     * Get foreign key relationships for a table.
     *
     * @param string $connectionName
     * @param string $tableName
     * @return array
     */
    protected function getForeignKeys(string $connectionName, string $tableName): array
    {
        $driver = $this->getConnectionDriver($connectionName);
        $connection = DB::connection($connectionName);
        
        $foreignKeys = [];
        
        if ($driver === 'sqlite') {
            $results = $connection->select("PRAGMA foreign_key_list({$tableName})");
            foreach ($results as $result) {
                $foreignKeys[] = [
                    'column' => $result->from,
                    'references_table' => $result->table,
                    'references_column' => $result->to,
                ];
            }
        } elseif ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $results = $connection->select("
                SELECT 
                    COLUMN_NAME as `column`,
                    REFERENCED_TABLE_NAME as references_table,
                    REFERENCED_COLUMN_NAME as references_column
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$database, $tableName]);
            
            foreach ($results as $result) {
                $foreignKeys[] = [
                    'column' => $result->column,
                    'references_table' => $result->references_table,
                    'references_column' => $result->references_column,
                ];
            }
        }
        
        return $foreignKeys;
    }

    /**
     * Resolve table dependencies using topological sort.
     *
     * @param string $connectionName
     * @return array
     * @throws Exception
     */
    protected function resolveDependencies(string $connectionName): array
    {
        $tables = $this->getTables($connectionName);
        
        // Build dependency graph
        $graph = [];
        $inDegree = [];
        
        foreach ($tables as $table) {
            $graph[$table] = [];
            $inDegree[$table] = 0;
        }
        
        foreach ($tables as $table) {
            $foreignKeys = $this->getForeignKeys($connectionName, $table);
            foreach ($foreignKeys as $fk) {
                $referencedTable = $fk['references_table'];
                
                // Only add dependency if referenced table is in our table list
                if (in_array($referencedTable, $tables) && $referencedTable !== $table) {
                    $graph[$referencedTable][] = $table;
                    $inDegree[$table]++;
                }
            }
        }
        
        // Detect circular dependencies
        $circular = $this->detectCircularDependencies($graph, $inDegree);
        if (!empty($circular)) {
            $circularList = implode(', ', $circular);
            throw new Exception("Circular dependencies detected: {$circularList}");
        }
        
        // Perform topological sort
        return $this->topologicalSort($graph, $inDegree);
    }

    /**
     * Detect circular dependencies in the dependency graph.
     *
     * @param array $graph
     * @param array $inDegree
     * @return array
     */
    protected function detectCircularDependencies(array $graph, array $inDegree): array
    {
        $queue = [];
        $processed = 0;
        
        // Find all nodes with no incoming edges
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }
        
        // Process nodes
        while (!empty($queue)) {
            $table = array_shift($queue);
            $processed++;
            
            foreach ($graph[$table] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }
        
        // If not all nodes were processed, there's a cycle
        if ($processed !== count($graph)) {
            return array_keys(array_filter($inDegree, fn($degree) => $degree > 0));
        }
        
        return [];
    }

    /**
     * Perform topological sort on the dependency graph.
     *
     * @param array $graph
     * @param array $inDegree
     * @return array
     */
    protected function topologicalSort(array $graph, array $inDegree): array
    {
        $queue = [];
        $sorted = [];
        
        // Find all nodes with no incoming edges
        foreach ($inDegree as $table => $degree) {
            if ($degree === 0) {
                $queue[] = $table;
            }
        }
        
        // Process nodes in dependency order
        while (!empty($queue)) {
            $table = array_shift($queue);
            $sorted[] = $table;
            
            foreach ($graph[$table] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }
        
        return $sorted;
    }

    /**
     * Validate that a connection exists in the configuration.
     *
     * @param string $connectionName
     * @return bool
     */
    protected function validateConnection(string $connectionName): bool
    {
        $connections = Config::get('database.connections', []);
        return array_key_exists($connectionName, $connections);
    }

    /**
     * Test actual connectivity to a database connection.
     *
     * @param string $connectionName
     * @return bool
     */
    protected function testConnection(string $connectionName): bool
    {
        try {
            DB::connection($connectionName)->getPdo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the database driver for a connection.
     *
     * @param string $connectionName
     * @return string
     */
    protected function getConnectionDriver(string $connectionName): string
    {
        return Config::get("database.connections.{$connectionName}.driver", 'unknown');
    }

    /**
     * Display available database connections.
     *
     * @return void
     */
    protected function displayAvailableConnections(): void
    {
        $connections = Config::get('database.connections', []);
        
        if (empty($connections)) {
            $this->warn('No database connections configured.');
            return;
        }

        $this->info('Available connections:');
        foreach ($connections as $name => $config) {
            $driver = $config['driver'] ?? 'unknown';
            $this->line("  - {$name} ({$driver})");
        }
    }

    /**
     * Confirm fresh migration with user.
     *
     * @param string $targetConnection
     * @param string $targetDriver
     * @return bool
     */
    protected function confirmFreshMigration(string $targetConnection, string $targetDriver): bool
    {
        $this->warn('WARNING: The --fresh option will truncate all tables in the target database.');
        $this->warn("Target: {$targetConnection} ({$targetDriver})");
        
        return $this->confirm('Are you sure you want to continue?', false);
    }

    /**
     * Migrate all tables from source to target connection.
     *
     * @param array $tables
     * @param string $sourceConnection
     * @param string $targetConnection
     * @param array $options
     * @return array
     * @throws Exception
     */
    protected function migrateTables(array $tables, string $sourceConnection, string $targetConnection, array $options): array
    {
        $stats = [
            'tables_migrated' => 0,
            'total_records' => 0,
            'skipped_records' => 0,
            'records_per_table' => [],
            'skipped_per_table' => [],
            'start_time' => now(),
        ];

        try {
            // Disable foreign key checks on target
            if (!$options['dry_run']) {
                $this->disableForeignKeyChecks($targetConnection);
            }

            // Truncate tables if fresh option is set
            if ($options['fresh'] && !$options['dry_run']) {
                $this->info('Truncating tables in dependency order...');
                $this->truncateTables($tables, $targetConnection);
            }

            // Migrate each table
            foreach ($tables as $table) {
                $result = $this->migrateTable($table, $sourceConnection, $targetConnection, $options);
                
                $stats['tables_migrated']++;
                $stats['total_records'] += $result['migrated'];
                $stats['skipped_records'] += $result['skipped'];
                $stats['records_per_table'][$table] = $result['migrated'];
                
                if ($result['skipped'] > 0) {
                    $stats['skipped_per_table'][$table] = $result['skipped'];
                }
            }

            // Re-enable foreign key checks on target
            if (!$options['dry_run']) {
                $this->enableForeignKeyChecks($targetConnection);
            }

        } catch (Exception $e) {
            // Re-enable foreign key checks on error
            if (!$options['dry_run']) {
                try {
                    $this->enableForeignKeyChecks($targetConnection);
                } catch (Exception $fkException) {
                    // Log but don't throw - we want to throw the original exception
                    $this->warn('Failed to re-enable foreign key checks: ' . $fkException->getMessage());
                }
            }
            throw $e;
        }

        $stats['end_time'] = now();
        $stats['duration_seconds'] = $stats['end_time']->diffInSeconds($stats['start_time']);

        return $stats;
    }

    /**
     * Migrate a single table from source to target connection.
     *
     * @param string $table
     * @param string $sourceConnection
     * @param string $targetConnection
     * @param array $options
     * @return array Array with 'migrated' and 'skipped' counts
     * @throws Exception
     */
    protected function migrateTable(string $table, string $sourceConnection, string $targetConnection, array $options): array
    {
        $recordCount = DB::connection($sourceConnection)->table($table)->count();
        
        // Display table header
        $this->newLine();
        $this->info("Migrating: {$table}");
        
        if ($recordCount === 0) {
            $this->line("  No records to migrate");
            return ['migrated' => 0, 'skipped' => 0];
        }

        if ($options['verbose']) {
            $this->line("  Total records: " . number_format($recordCount));
        }
        
        $recordsMigrated = 0;
        $recordsSkipped = 0;
        $chunkSize = 1000;

        // Create progress bar
        $progressBar = $this->output->createProgressBar($recordCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% ');
        $progressBar->start();

        try {
            // Wrap in transaction for target database (skip transaction in dry-run mode)
            $migrateFunction = function () use (
                $table, 
                $sourceConnection, 
                $targetConnection, 
                $options, 
                $chunkSize, 
                $progressBar,
                &$recordsMigrated,
                &$recordsSkipped
            ) {
                // Read and write data in chunks
                DB::connection($sourceConnection)
                    ->table($table)
                    ->orderBy(DB::raw('1')) // Order by first column for consistent chunking
                    ->chunk($chunkSize, function ($records) use (
                        $table, 
                        $targetConnection, 
                        $options, 
                        $progressBar,
                        &$recordsMigrated,
                        &$recordsSkipped
                    ) {
                        if (!$options['dry_run']) {
                            // Convert records to array format for batch insert
                            $recordsArray = array_map(function ($record) {
                                return (array) $record;
                            }, $records->all());
                            
                            // Handle duplicate records when not using fresh option
                            if (!$options['fresh']) {
                                $result = $this->insertWithDuplicateHandling(
                                    $table, 
                                    $recordsArray, 
                                    $targetConnection,
                                    $options
                                );
                                $recordsMigrated += $result['inserted'];
                                $recordsSkipped += $result['skipped'];
                            } else {
                                // Batch insert records (fresh mode - no duplicates expected)
                                DB::connection($targetConnection)->table($table)->insert($recordsArray);
                                $recordsMigrated += count($records);
                            }
                        } else {
                            // Dry-run mode: just count records
                            $recordCount = count($records);
                            $recordsMigrated += $recordCount;
                            
                            // Display sample records in verbose mode (only first chunk)
                            if ($options['verbose'] && $recordsMigrated <= 1000) {
                                foreach ($records as $record) {
                                    $progressBar->clear();
                                    $this->line("    [DRY-RUN] Would insert: " . json_encode((array)$record));
                                    $progressBar->display();
                                }
                            }
                        }
                        
                        // Update progress bar
                        $progressBar->advance(count($records));
                        
                        return true; // Continue chunking
                    });
            };

            if (!$options['dry_run']) {
                DB::connection($targetConnection)->transaction($migrateFunction);
            } else {
                $migrateFunction();
            }

            $progressBar->finish();
            $this->newLine();
            
            $message = "  ✓ Completed: " . number_format($recordsMigrated) . " records";
            if ($options['dry_run']) {
                $message .= " (DRY-RUN - no data written)";
            } else {
                $message .= " migrated";
            }
            if ($recordsSkipped > 0) {
                $message .= ", " . number_format($recordsSkipped) . " skipped (duplicates)";
            }
            $this->info($message);
            
        } catch (Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error("  ✗ Failed to migrate table {$table}: " . $e->getMessage());
            throw $e;
        }

        return ['migrated' => $recordsMigrated, 'skipped' => $recordsSkipped];
    }

    /**
     * Truncate tables in reverse dependency order.
     *
     * @param array $tables Tables in dependency order
     * @param string $connectionName
     * @return void
     */
    protected function truncateTables(array $tables, string $connectionName): void
    {
        // Truncate in reverse order (children before parents)
        foreach (array_reverse($tables) as $table) {
            $this->truncateTable($table, $connectionName);
        }
    }

    /**
     * Truncate a table in the specified connection.
     *
     * @param string $table
     * @param string $connectionName
     * @return void
     */
    protected function truncateTable(string $table, string $connectionName): void
    {
        try {
            DB::connection($connectionName)->table($table)->truncate();
            $this->line("  Truncated: {$table}");
        } catch (Exception $e) {
            $this->warn("  Failed to truncate {$table}: " . $e->getMessage());
        }
    }

    /**
     * Insert records with duplicate handling (skip on unique constraint violation).
     *
     * @param string $table
     * @param array $records
     * @param string $connectionName
     * @param array $options
     * @return array Array with 'inserted' and 'skipped' counts
     */
    protected function insertWithDuplicateHandling(string $table, array $records, string $connectionName, array $options): array
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($records as $record) {
            try {
                DB::connection($connectionName)->table($table)->insert($record);
                $inserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                // Check if it's a unique constraint violation
                if ($this->isUniqueConstraintViolation($e)) {
                    $skipped++;
                    // Log skipped record in verbose mode
                    if ($options['verbose'] ?? false) {
                        $this->newLine();
                        $this->line("    Skipped duplicate record in {$table}: " . json_encode($record));
                    }
                } else {
                    // Re-throw if it's not a duplicate error
                    throw $e;
                }
            }
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Check if an exception is a unique constraint violation.
     *
     * @param \Illuminate\Database\QueryException $e
     * @return bool
     */
    protected function isUniqueConstraintViolation(\Illuminate\Database\QueryException $e): bool
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // SQLite: SQLSTATE[23000] - Integrity constraint violation
        // MySQL: Error code 1062 - Duplicate entry
        return $errorCode === '23000' || 
               str_contains($errorMessage, 'Duplicate entry') ||
               str_contains($errorMessage, 'UNIQUE constraint failed') ||
               str_contains($errorMessage, 'duplicate key value');
    }

    /**
     * Disable foreign key checks for the specified connection.
     *
     * @param string $connectionName
     * @return void
     */
    protected function disableForeignKeyChecks(string $connectionName): void
    {
        $driver = $this->getConnectionDriver($connectionName);
        
        if ($driver === 'sqlite') {
            DB::connection($connectionName)->statement('PRAGMA foreign_keys = OFF');
        } elseif ($driver === 'mysql') {
            DB::connection($connectionName)->statement('SET FOREIGN_KEY_CHECKS = 0');
        }
    }

    /**
     * Enable foreign key checks for the specified connection.
     *
     * @param string $connectionName
     * @return void
     */
    protected function enableForeignKeyChecks(string $connectionName): void
    {
        $driver = $this->getConnectionDriver($connectionName);
        
        if ($driver === 'sqlite') {
            DB::connection($connectionName)->statement('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'mysql') {
            DB::connection($connectionName)->statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Display migration summary statistics.
     *
     * @param array $stats
     * @return void
     */
    protected function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->newLine();
        $this->info('╔════════════════════════════════════════╗');
        $this->info('║       Migration Summary                ║');
        $this->info('╚════════════════════════════════════════╝');
        $this->newLine();
        
        $this->info("  Tables migrated:  " . number_format($stats['tables_migrated']));
        $this->info("  Total records:    " . number_format($stats['total_records']));
        
        if ($stats['skipped_records'] > 0) {
            $this->warn("  Skipped records:  " . number_format($stats['skipped_records']) . " (duplicates)");
        }
        
        $duration = $this->formatDuration($stats['duration_seconds']);
        $this->info("  Duration:         {$duration}");
        
        if ($this->option('verbose') && !empty($stats['records_per_table'])) {
            $this->newLine();
            $this->info('Records per table:');
            foreach ($stats['records_per_table'] as $table => $count) {
                $skipped = $stats['skipped_per_table'][$table] ?? 0;
                $line = "  • {$table}: " . number_format($count);
                if ($skipped > 0) {
                    $line .= " (skipped: " . number_format($skipped) . ")";
                }
                $this->line($line);
            }
        }
        
        $this->newLine();
    }

    /**
     * Format duration in a human-readable format.
     *
     * @param float $seconds
     * @return string
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $minutes . 'm ' . round($remainingSeconds) . 's';
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return $hours . 'h ' . $remainingMinutes . 'm ' . round($remainingSeconds) . 's';
    }
}
