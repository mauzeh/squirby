# Implementation Plan

- [x] 1. Create console command structure and validation
  - Create `MigrateDatabaseCommand` in `app/Console/Commands/MigrateDatabaseCommand.php`
  - Define command signature with options: `--from`, `--to`, `--fresh`, `--dry-run`, `--verbose`
  - Implement connection validation to verify source and target connections exist
  - Implement method to display available connections when validation fails
  - Add confirmation prompt for `--fresh` option
  - _Requirements: 1.1, 1.2, 3.1, 3.2, 3.3, 4.1, 4.2_

- [x] 2. Implement schema analysis and table discovery
  - Create method to get all table names from a connection
  - Create method to get foreign key relationships for each table
  - Implement dependency resolution using topological sort
  - Handle circular dependency detection and reporting
  - Exclude Laravel system tables (migrations, cache, sessions, jobs)
  - _Requirements: 1.1, 2.3_

- [x] 3. Implement core migration engine
  - Create method to migrate a single table with chunked reading
  - Implement data reading using Laravel's `chunk()` method (1000 records per chunk)
  - Implement batch insertion to target database
  - Add foreign key constraint disabling/enabling for both SQLite and MySQL
  - Wrap migration in database transaction with rollback on error
  - _Requirements: 1.1, 1.3, 2.1, 2.3, 2.4, 2.5_

- [x] 4. Implement fresh migration and duplicate handling
  - Create method to truncate tables in dependency order
  - Implement duplicate record handling (skip on unique constraint violation)
  - Add logging for skipped records
  - Ensure foreign keys are disabled during truncation
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

- [x] 5. Implement progress reporting and dry-run mode
  - Create progress bar for each table migration
  - Display current table name and record count
  - Implement verbose mode with detailed record information
  - Implement dry-run mode that validates without writing data
  - Display final summary with tables migrated, total records, and duration
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 6. Add error handling and edge cases
  - Handle empty tables gracefully
  - Handle missing foreign key references (orphaned records)
  - Add proper error messages for connection failures
  - Add proper error messages for schema mismatches
  - Handle transaction rollback failures
  - _Requirements: 2.5_

- [x] 7. Write unit tests for core components
  - Write tests for connection validation
  - Write tests for schema analysis and table discovery
  - Write tests for dependency resolution and topological sort
  - Write tests for circular dependency detection
  - Write tests for data chunking logic
  - Write tests for duplicate handling
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5_

- [ ]* 8. Write feature tests for end-to-end migration
  - Write test for SQLite to MySQL migration with sample data
  - Write test for MySQL to SQLite migration with sample data
  - Write test for --fresh option behavior
  - Write test for --dry-run option behavior
  - Write test for invalid connection handling
  - Write test for progress reporting output
  - Write test for error recovery and rollback
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 2.5, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4, 6.5_
