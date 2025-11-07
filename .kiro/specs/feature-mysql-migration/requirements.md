# Requirements Document

## Introduction

This feature provides a console command to migrate all database data directly from one database connection to another. This enables seamless migration between different database systems (e.g., SQLite to MySQL, PostgreSQL to SQLite) by piping data directly between configured database connections without intermediate files.

## Glossary

- **Migration Command**: A console command that transfers all data from a source database connection to a target database connection
- **Source Connection**: The Laravel database connection from which data is being read (can be any Laravel-supported database)
- **Target Connection**: The Laravel database connection to which data is being written (can be any Laravel-supported database)
- **Database Connection**: A configured database connection in Laravel's config/database.php
- **Console Command**: An Artisan command that can be executed from the command line
- **Piping**: Transferring data directly from source to target without intermediate storage

## Requirements

### Requirement 1

**User Story:** As a developer, I want to migrate all database data from one database connection to another, so that I can switch between different database systems seamlessly

#### Acceptance Criteria

1. THE Migration Command SHALL accept a source connection name and a target connection name as arguments
2. THE Migration Command SHALL read all data from all tables in the Source Connection using Laravel's database abstraction layer
3. THE Migration Command SHALL write all data to corresponding tables in the Target Connection using Laravel's database abstraction layer
4. THE Migration Command SHALL transfer all user data, exercises, lift logs, food logs, body logs, meals, ingredients, programs, and related data
5. WHEN the migration completes successfully, THE Migration Command SHALL display a summary showing the number of records migrated per table

### Requirement 2

**User Story:** As a developer, I want the migration to preserve data integrity, so that all relationships and constraints are maintained in the target database

#### Acceptance Criteria

1. THE Migration Command SHALL migrate tables in an order that respects foreign key dependencies
2. THE Migration Command SHALL preserve all data relationships during migration regardless of source and target database types
3. THE Migration Command SHALL handle data type conversions automatically between different database systems
4. THE Migration Command SHALL wrap the entire migration operation in a database transaction
5. IF any error occurs during migration, THEN THE Migration Command SHALL roll back all changes to the Target Connection and display an error message

### Requirement 3

**User Story:** As a developer, I want to specify connection names from my Laravel configuration, so that I can use any configured database connection

#### Acceptance Criteria

1. THE Migration Command SHALL validate that both source and target connection names exist in Laravel's database configuration
2. WHERE no source connection is specified, THE Migration Command SHALL use the default database connection as the source
3. THE Migration Command SHALL require a target connection name to be explicitly specified
4. IF a specified connection does not exist, THEN THE Migration Command SHALL display an error message listing available connections
5. THE Migration Command SHALL display the source and target database drivers before starting the migration

### Requirement 4

**User Story:** As a developer, I want to control how existing data in the target database is handled, so that I can avoid data conflicts and choose between fresh migration or incremental updates

#### Acceptance Criteria

1. THE Migration Command SHALL provide a --fresh option to clear existing data before migrating
2. WHERE the --fresh option is specified, THE Migration Command SHALL truncate all tables in the Target Connection before migrating data
3. WHERE the --fresh option is not specified, THE Migration Command SHALL attempt to insert records and skip those that would violate unique constraints
4. THE Migration Command SHALL display a warning and require confirmation before truncating tables
5. THE Migration Command SHALL disable foreign key checks during truncation and re-enable them after migration completes

### Requirement 5

**User Story:** As a developer, I want to see progress during migration, so that I can monitor the operation and estimate completion time

#### Acceptance Criteria

1. WHILE the migration is in progress, THE Migration Command SHALL display the current table being migrated
2. THE Migration Command SHALL display a progress indicator showing the number of records migrated for each table
3. WHEN each table migration completes, THE Migration Command SHALL display the total records migrated for that table
4. THE Migration Command SHALL display the total migration time when the operation completes
5. THE Migration Command SHALL provide a --verbose option to display detailed information about each record being migrated

### Requirement 6

**User Story:** As a developer, I want to perform a dry run of the migration, so that I can verify what will be migrated without making actual changes

#### Acceptance Criteria

1. THE Migration Command SHALL provide a --dry-run option to simulate the migration without writing data
2. WHERE the --dry-run option is specified, THE Migration Command SHALL read data from the Source Connection but not write to the Target Connection
3. WHEN running in dry-run mode, THE Migration Command SHALL display the same progress and summary information as a real migration
4. THE Migration Command SHALL clearly indicate in the output when running in dry-run mode
5. THE Migration Command SHALL validate that the Target Connection is accessible during dry-run mode


