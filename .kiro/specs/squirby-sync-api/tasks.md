# Implementation Plan: Sync API

## Overview

Build a REST API under `/api/sync` providing durable storage and restoration for an external front-end app. Implementation follows schema-first → services → controllers → integration order, ensuring each layer is testable before wiring the next.

## Tasks

- [ ] 1. Database schema and model foundations
  - [ ] 1.1 Create migration for `athlete_blueprints` table
    - Columns: id, user_id (unique FK → users, cascade), blueprint_data (JSON), device_id (VARCHAR 36 nullable), timestamps
    - _Requirements: 1.1_

  - [ ] 1.2 Create migration for `athlete_preferences` table
    - Columns: id, user_id (unique FK → users, cascade), preferences_data (JSON), device_id (VARCHAR 36 nullable), timestamps
    - _Requirements: 1.2_

  - [ ] 1.3 Create migration to add columns to `lift_logs` table
    - Add: track (VARCHAR 20 nullable), block_index (TINYINT unsigned nullable), movement_index (TINYINT unsigned nullable), log_type (VARCHAR 30 nullable), device_id (VARCHAR 36 nullable), source (VARCHAR 10 nullable)
    - No uniqueness constraints — track/block_index/movement_index are optional hint columns only
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [ ] 1.4 Create migration to add columns to `lift_sets` table
    - Add: calories (SMALLINT unsigned nullable), distance (DECIMAL 8,2 nullable), distance_unit (VARCHAR 5 nullable)
    - _Requirements: 1.1_

  - [ ] 1.5 Create `AthleteBlueprint` model
    - Location: `App\Sync\Models\AthleteBlueprint`
    - Fillable: user_id, blueprint_data, device_id
    - Casts: blueprint_data → array
    - Relationship: belongsTo(User)
    - _Requirements: 3.1_

  - [ ] 1.6 Create `AthletePreference` model
    - Location: `App\Sync\Models\AthletePreference`
    - Fillable: user_id, preferences_data, device_id
    - Casts: preferences_data → array
    - Relationship: belongsTo(User)
    - _Requirements: 3.2_

  - [ ] 1.7 Update `LiftLog` model with new fillable fields
    - Add to fillable: track, block_index, movement_index, log_type, device_id, source
    - _Requirements: 2.1, 7.4_

  - [ ] 1.8 Update `LiftSet` model with new fillable fields
    - Add to fillable: calories, distance, distance_unit
    - _Requirements: 1.1_

- [ ] 2. Middleware, CORS, and routing infrastructure
  - [ ] 2.1 Create `EnsureDeviceId` middleware
    - Location: `App\Sync\Middleware\EnsureDeviceId`
    - Extract `X-Device-Id` header and bind to request attributes
    - Non-blocking: if absent, continue with null
    - Register as `device-id` alias in Kernel or route middleware
    - _Requirements: 10.4_

  - [ ] 2.2 Configure CORS for `/api/sync/*` routes
    - Add `'api/sync/*'` to the `paths` array in `config/cors.php`
    - Set allowed origins to wildcard during pilot
    - Allowed methods: GET, POST, DELETE, OPTIONS
    - Allowed headers: Authorization, Content-Type, X-Device-Id
    - _Requirements: 14.1, 14.2, 14.3_

  - [ ] 2.3 Create `routes/sync.php` and register in RouteServiceProvider
    - Create dedicated route file with all 7 endpoints
    - Register in RouteServiceProvider (or bootstrap/app.php) with prefix `api/sync`
    - Public routes: register, login
    - Authenticated group: auth:sanctum + device-id middleware
    - _Requirements: 13.1, 13.2_

- [ ] 3. Data migration and upstream logic changes
  - [ ] 3.1 Create data migration for existing cardio logs
    - Copy `lift_sets.reps` → `lift_sets.distance` for all lift_sets belonging to lift_logs whose exercise has exercise_type = 'cardio'
    - Set `lift_sets.distance_unit` = 'm' for migrated rows
    - Set `lift_sets.reps` = NULL for migrated rows (distance was stored here as a hack)
    - Ensure migration is reversible (store original value or copy back on rollback)
    - _Requirements: 4.1, 4.2, 4.3, 4.4_

  - [ ] 3.2 Update `CardioExerciseType` strategy
    - `processLiftData()`: store distance in the `distance` field (not `reps`) going forward
    - `calculateCurrentMetrics()`: read from `$set->distance` instead of `$set->reps`
    - `compareToPrevious()`: same — use `distance` column
    - `formatWeightDisplay()`: read from `$set->distance` or `$liftLog->display_distance`
    - `formatCompleteDisplay()`: update to use distance column
    - `getFormFieldDefinitions()`: change field name from 'reps' to 'distance' (or keep UI label but map to correct column)
    - All helper methods (`getBestSingleDistance`, `getBestTotalDistance`, `getBestDistanceForRounds`): read `$set->distance`
    - _Requirements: 4.5, 4.6_

  - [ ] 3.3 Update `CardioProgressionChartGenerator`
    - Change from `$liftLog->display_reps` to reading the `distance` column from lift_sets
    - Total distance = sum of `$liftLog->liftSets->sum('distance')` × rounds logic (if applicable)
    - _Requirements: 4.5_

  - [ ] 3.4 Update `LiftLog` model display accessors
    - Add `getDisplayDistanceAttribute()` that returns `$this->liftSets->first()->distance ?? 0`
    - Ensure `getDisplayRepsAttribute()` still works for non-cardio exercises (it already reads from `reps`, which is now null for cardio — returns 0, which is correct)
    - _Requirements: 4.5, 4.7_

  - [ ] 3.5 Update `CreateLiftLogAction` and `UpdateLiftLogAction`
    - The `createLiftSets` / `updateLiftSets` methods currently build sets from strategy output — ensure the cardio strategy's new output (distance in `distance` field) is passed through to `liftSets()->create()`
    - Add `distance`, `distance_unit`, `calories` to the fields written in set creation
    - _Requirements: 4.6_

  - [ ] 3.6 Verify existing tests still pass
    - Run full test suite after migration + strategy changes
    - Specifically verify: CardioExerciseTypeTest, CardioProgressionChartGenerator tests, PRDetectionService tests for cardio exercises
    - _Requirements: 4.7_

- [ ] 4. Checkpoint — Data migration and upstream changes verified
  - Ensure migrations run cleanly on existing data, web UI cardio logging still works, charts still render, PR detection still functions. Ask the user if questions arise.

- [ ] 5. Services layer
  - [ ] 5.1 Create `SetFieldMapper` service
    - Location: `App\Sync\Services\SetFieldMapper`
    - Implement `mapToColumns(string $logType, array $setData, string $weightUnit): array` — maps incoming set fields to DB columns
    - Implement `mapFromColumns(string $logType, LiftSet $set): array` — reconstructs original field names from DB columns for restore
    - Handle all 14 log types per the mapping table in the design
    - Always include `unit` (weight_unit) on mapToColumns output
    - _Requirements: 15.1–15.14_

  - [ ] 5.4 Create `ExerciseResolverService`
    - Location: `App\Sync\Services\ExerciseResolverService`
    - Implement `resolve(string $exerciseName, User $user, ?string $logType = null): Exercise`
    - Priority: canonical_name (Str::snake) → title (case-insensitive) → alias (case-insensitive) → auto-create
    - Scope lookups to global + user-owned, exclude soft-deleted
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6_

- [ ] 6. Checkpoint — Services verified
  - Ensure services instantiate and basic logic works. Ask the user if questions arise.

- [ ] 7. Actions layer
  - [ ] 7.1 Create `StoreSyncLogAction`
    - Location: `App\Sync\Actions\StoreSyncLogAction`
    - Inject ExerciseResolverService and SetFieldMapper
    - Implement `execute(User $user, array $validated, ?string $deviceId): LiftLog`
    - Create lift_log, create lift_sets via SetFieldMapper, dispatch event
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [ ] 7.2 Create `DeleteSyncLogAction`
    - Location: `App\Sync\Actions\DeleteSyncLogAction`
    - Implement `execute(User $user, LiftLog $liftLog): void`
    - Verify ownership, soft-delete log and sets
    - _Requirements: 8.1, 8.2_

- [ ] 8. Controllers layer
  - [ ] 8.1 Create `AuthController`
    - Location: `App\Sync\Controllers\AuthController`
    - `register`: validate username/password/device_id, create user with generated email, generate Sanctum token, return response
    - `login`: validate credentials, authenticate, return token
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 6.3, 6.4_

  - [ ] 8.2 Create `LogController`
    - Location: `App\Sync\Controllers\LogController`
    - `store`: validate payload, delegate to StoreSyncLogAction, return { status: "ok", log_id }
    - `destroy`: validate ownership, delegate to DeleteSyncLogAction, return { status: "ok" }
    - _Requirements: 7.1, 7.6, 7.7, 8.1, 8.2, 8.3_

  - [ ] 8.3 Create `BlueprintController`
    - Location: `App\Sync\Controllers\BlueprintController`
    - `store`: upsert athlete_blueprints row for authenticated user, store body as blueprint_data verbatim
    - _Requirements: 9.1, 9.2, 9.3, 9.4_

  - [ ] 8.4 Create `PreferencesController`
    - Location: `App\Sync\Controllers\PreferencesController`
    - `store`: upsert athlete_preferences row for authenticated user, store body as preferences_data verbatim
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

  - [ ] 8.5 Create `RestoreController`
    - Location: `App\Sync\Controllers\RestoreController`
    - `index`: return blueprint, preferences, logs (as array with all fields), and prHistory
    - Return ALL user logs regardless of source
    - PR history: current (non-superseded) records grouped by exercise canonical_name
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6_

- [ ] 9. Checkpoint — Controllers wired
  - Ensure all routes respond, auth middleware blocks unauthenticated requests, and basic request/response cycles work. Ask the user if questions arise.

- [ ] 10. Error handling, request logging, and operations
  - [ ] 10.1 Add scoped exception handling for `/api/sync` routes
    - ValidationException → 422 with { status: "error", message }
    - AuthenticationException → 401 with { status: "error", message }
    - All other exceptions → 500 with generic message (no internal details)
    - Scoped to sync routes only to avoid affecting existing web app
    - _Requirements: 16.1, 16.2, 16.3, 16.4_

  - [ ] 10.2 Create `LogSyncRequest` middleware
    - Location: `App\Sync\Middleware\LogSyncRequest`
    - Log full request (all headers, body, method, path, query, IP, timestamp) as a JSON line to `storage/logs/sync/requests.log` BEFORE any processing
    - Logging failure must not block the request (wrap in try/catch)
    - Configure `sync_requests` log channel in `config/logging.php` as daily rotating file at `storage/logs/sync/requests.log`
    - Register as `log-sync-request` middleware alias
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5_

  - [ ] 10.3 Create `sync:purge-logs` CLI command
    - Location: `App\Sync\Commands\PurgeSyncLogs`
    - Delete log files in `storage/logs/sync/` older than N days (default 30)
    - Support --days=N flag
    - _Requirements: 17.6_

  - [ ] 10.4 Create `sync:replay-failed` CLI command
    - Location: `App\Sync\Commands\ReplayFailedRequests`
    - Parse log entries from `storage/logs/sync/requests.log`
    - Support filtering by date range, endpoint, or user
    - Replay selected entries through the controller logic
    - Support --dry-run flag to preview without executing
    - _Requirements: 17.7_

  - [ ] 10.5 Write `docs/sync-api-operations.md`
    - Explain the request logging setup (filesystem, JSON lines, daily rotation)
    - Where logs live: `storage/logs/sync/requests-YYYY-MM-DD.log`
    - How to find failed requests (grep for specific status codes or errors in application logs)
    - How to replay requests (`php artisan sync:replay-failed`)
    - How to purge old logs (`php artisan sync:purge-logs --days=30`)
    - _Requirements: 17.8_

- [ ] 11. Tests
  - [ ] 11.1 Write unit tests for SetFieldMapper
    - One test per log type for `mapToColumns` (verify exact column output)
    - One test per log type for `mapFromColumns` (verify correct field name reconstruction)
    - Location: `tests/Unit/Sync/SetFieldMapperTest.php`
    - _Requirements: 15.1–15.14_

  - [ ] 11.2 Write unit tests for ExerciseResolverService
    - Test canonical_name match, title match, alias match, auto-create
    - Test scoping (global + user-owned), test soft-delete exclusion
    - Location: `tests/Unit/Sync/ExerciseResolverServiceTest.php`
    - _Requirements: 12.1–12.6_

  - [ ] 11.3 Write unit tests for StoreSyncLogAction
    - Test log + sets creation, event dispatch, exercise resolution integration
    - Location: `tests/Unit/Sync/StoreSyncLogActionTest.php`
    - _Requirements: 7.1–7.9_

  - [ ] 11.4 Write unit tests for DeleteSyncLogAction
    - Test soft-delete cascading, ownership check
    - Location: `tests/Unit/Sync/DeleteSyncLogActionTest.php`
    - _Requirements: 8.1–8.3_

  - [ ] 11.5 Write smoke tests (2 feature tests using Laravel's in-process test client)
    - `test_full_write_and_restore_cycle`: register → store log → store blueprint → store preferences → restore → verify
    - `test_auth_and_error_responses`: 401 on missing token, 422 on validation failure, 404 on wrong-user delete
    - Location: `tests/Feature/Sync/SyncApiSmokeTest.php`
    - _Requirements: 5.1, 7.1, 8.1, 9.1, 10.1, 11.1, 13.1, 16.1_

- [ ] 12. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- All tests run in PHPUnit (no additional framework)
- Unit tests cover services and actions in isolation
- 2 smoke tests verify the full HTTP cycle using Laravel's in-process test client (no real network calls)
- No uniqueness constraints on lift_logs — logs are standalone entities identified by ID
- All sync code lives in `app/Sync/` domain folder — one folder to find, grep, or delete
- Only routes (`routes/api.php`), migrations (`database/migrations/`), and CORS config remain in standard Laravel locations

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4"] },
    { "id": 1, "tasks": ["1.5", "1.6", "1.7", "1.8"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3"] },
    { "id": 3, "tasks": ["3.1"] },
    { "id": 4, "tasks": ["3.2", "3.3", "3.4", "3.5"] },
    { "id": 5, "tasks": ["3.6"] },
    { "id": 6, "tasks": ["5.1", "5.4"] },
    { "id": 7, "tasks": ["7.1", "7.2"] },
    { "id": 8, "tasks": ["8.1", "8.2", "8.3", "8.4", "8.5"] },
    { "id": 9, "tasks": ["10.1"] },
    { "id": 10, "tasks": ["11.1", "11.2", "11.3", "11.4", "11.5"] }
  ]
}
```
