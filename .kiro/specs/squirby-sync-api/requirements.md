# Requirements Document

## Introduction

This spec covers building a Sync API — a set of REST endpoints that allow an external front-end application to durably store and restore workout completion logs, personalization blueprints, and user preferences. The front-end uses localStorage as its source of truth and syncs here as a fire-and-forget backup. On cache clear, the front-end restores its full state from these endpoints.

Additionally, the lift_sets schema must be extended to support richer data (distance, calories) that the front-end tracks, while maintaining backward compatibility with existing web UI logging flows.

All endpoints live under `/api/sync` and use Laravel Sanctum for per-user authentication.

## Glossary

- **Sync API**: The set of Laravel API endpoints under `/api/sync` that serve as durable storage and restoration for an external front-end app.
- **Exercise Resolver**: A service that matches exercise names (strings) to existing Exercise records using canonical_name, title, and alias lookups — auto-creating when no match is found.
- **Blueprint**: An opaque JSON blob representing personalization state. The API stores it verbatim — never interprets or validates its contents.
- **Preferences**: An opaque JSON blob representing user settings. Same storage-only contract as blueprint.
- **PR History**: Current (non-superseded) personal records for a user, grouped by exercise, returned during restore to seed client-side PR detection.
- **Device ID**: A UUID per client device, sent via the `X-Device-Id` header on every request.
- **Log Type**: A string identifying how a movement was logged (e.g., "barbell", "cardio", "static-hold"). Determines how set fields map to database columns.

## Requirements

### Requirement 1: Schema Migrations — lift_sets extension

**User Story:** As a developer, I want lift_sets to support distance, calories, and raw set data, so that all movement types from the front-end can be stored without hacking existing columns.

#### Acceptance Criteria

1. The `lift_sets` table SHALL gain columns: distance (DECIMAL 8,2 nullable), distance_unit (VARCHAR 5 nullable), calories (SMALLINT unsigned nullable)
2. Existing data SHALL NOT be modified — new columns are nullable and default to null
3. Existing services (PR detection, charts, 1RM calculator) SHALL continue to function unchanged for web-UI-created logs

### Requirement 2: Schema Migrations — lift_logs extension

**User Story:** As a developer, I want lift_logs to carry metadata about how and where a log was created, so the API can identify sync-originated logs and store context for rare same-exercise-twice display resolution.

#### Acceptance Criteria

1. The `lift_logs` table SHALL gain columns: log_type (VARCHAR 30 nullable), device_id (VARCHAR 36 nullable), source (VARCHAR 10 nullable, default null — values: 'sync' or null for web UI), track (VARCHAR 20 nullable), block_index (TINYINT unsigned nullable), movement_index (TINYINT unsigned nullable)
2. track, block_index, and movement_index are optional hint columns — they carry no uniqueness constraint and exist only so the front-end can resolve which program slot a log belongs to when the same exercise appears twice on the same day
3. Existing web-UI-created logs SHALL have source = null, track = null, block_index = null, movement_index = null (unchanged)
4. No unique index SHALL be created on these columns — multiple logs for the same exercise on the same day are valid

### Requirement 3: Schema Migrations — new tables

**User Story:** As a developer, I want new tables for blueprint and preference storage, so that personalization data can survive cache clears.

#### Acceptance Criteria

1. A new `athlete_blueprints` table SHALL be created with columns: id (auto-increment PK), user_id (unique FK to users, cascade on delete), blueprint_data (JSON), device_id (VARCHAR 36 nullable), created_at, updated_at
2. A new `athlete_preferences` table SHALL be created with columns: id (auto-increment PK), user_id (unique FK to users, cascade on delete), preferences_data (JSON), device_id (VARCHAR 36 nullable), created_at, updated_at

### Requirement 4: Data migration — cardio logs

**User Story:** As a developer, I want existing cardio logs (which store distance in the reps column) migrated to use the new distance column, so that all logs have a consistent shape regardless of creation source.

#### Acceptance Criteria

1. A data migration SHALL copy `lift_sets.reps` → `lift_sets.distance` for all lift_sets belonging to lift_logs whose exercise has exercise_type = 'cardio'
2. The migration SHALL set `lift_sets.distance_unit` = 'm' for migrated rows (existing Logger always stores meters)
3. The migration SHALL set `lift_sets.reps` = null for migrated rows (cardio has no meaningful "reps" — it was a hack)
4. The migration SHALL be reversible (store original reps value in case of rollback)
5. The `CardioExerciseType` strategy and `CardioProgressionChartGenerator` SHALL be updated to read from `lift_sets.distance` instead of `lift_sets.reps`
6. The `CardioExerciseType.processLiftData()` SHALL store distance in the `distance` column (not reps) for new logs going forward
7. The `VolumeProgressionChartGenerator` and any other service that sums `reps` SHALL be unaffected (cardio reps will be null after migration, sum of null = 0)

### Requirement 5: User Registration

**User Story:** As a new user, I want to create an account with a username and password, so that I can authenticate and persist my data.

#### Acceptance Criteria

1. WHEN a valid POST /api/sync/register request is received with username, password, and device_id, the API SHALL create a new user, generate a Sanctum personal access token, and return { status: "ok", token, athlete: username }
2. The username SHALL be stored as users.name. An email SHALL be generated in the format `{username}@sync.local` (users never log in via email — this satisfies the non-nullable email column)
3. WHEN the username already exists, the API SHALL return HTTP 422 with a descriptive error
4. The password SHALL be at least 6 characters
5. The device_id from the request SHALL be stored for tracking purposes

### Requirement 6: User Login

**User Story:** As a returning user, I want to log in with my username and password, so that I can obtain an auth token for API calls.

#### Acceptance Criteria

1. WHEN a valid POST /api/sync/login request is received with correct credentials, the API SHALL return { status: "ok", token, athlete: username }
2. WHEN credentials are invalid, the API SHALL return HTTP 401 with { status: "error", message }
3. The device_id SHALL be recorded on login
4. Multiple active tokens per user SHALL be allowed (one per device)

### Requirement 7: Store Completion Log

**User Story:** As an authenticated user, I want to store a workout movement log, so that my data is durably backed up.

#### Acceptance Criteria

1. WHEN an authenticated POST /api/sync/logs request is received, the API SHALL resolve the exercise_name to an Exercise record (via the Exercise Resolver) and create a lift_log with associated lift_sets
2. The user SHALL be derived from the Sanctum token, not from the request body
3. Each set object SHALL be stored as a lift_set row with fields mapped by log_type (see Requirement 15), and the unit set from the request's weight_unit field
4. The lift_log SHALL store: log_type, device_id, source = 'sync', and the note field as comments
5. logged_at SHALL be set to the request's date field (as datetime at 12:00:00)
6. On success, the API SHALL return { status: "ok", log_id }
7. Validation SHALL require: exercise_name, date, log_type, sets — returning 422 on failure
8. Multiple logs for the same exercise on the same day SHALL be allowed (the front-end determines how to display them)
9. The request MAY include track, block_index, and movement_index as optional fields — if provided, they SHALL be stored on the lift_log as display hints for the front-end

### Requirement 8: Delete Completion Log

**User Story:** As an authenticated user, I want to delete a previously synced log by ID, so that deletions are reflected in the backend.

#### Acceptance Criteria

1. WHEN an authenticated DELETE /api/sync/logs/{id} request is received, the API SHALL soft-delete the lift_log and its associated lift_sets, but only if it belongs to the authenticated user
2. If the log doesn't exist or doesn't belong to the user, the API SHALL return HTTP 404
3. On success, return { status: "ok" }

### Requirement 9: Store Blueprint

**User Story:** As an authenticated user, I want to store my personalization blueprint, so that it survives a cache clear.

#### Acceptance Criteria

1. WHEN an authenticated POST /api/sync/blueprint request is received, the API SHALL upsert an athlete_blueprints row for the user, storing the request body (minus device_id) as blueprint_data JSON
2. The device_id SHALL be stored on the row
3. blueprint_data SHALL be stored verbatim — the API SHALL NOT interpret, validate, or transform its contents
4. On success, return { status: "ok" }

### Requirement 10: Store Preferences

**User Story:** As an authenticated user, I want to store my app preferences, so that they survive a cache clear.

#### Acceptance Criteria

1. WHEN an authenticated POST /api/sync/preferences request is received, the API SHALL upsert an athlete_preferences row for the user, storing the request body (minus device_id) as preferences_data JSON
2. The device_id SHALL be stored on the row
3. preferences_data SHALL be stored verbatim — the API SHALL NOT interpret, validate, or transform its contents
4. On success, return { status: "ok" }

### Requirement 11: Restore Full State

**User Story:** As an authenticated user, I want to restore my full state after a cache clear — logs, blueprint, preferences, and PR history — in a single request.

#### Acceptance Criteria

1. WHEN an authenticated GET /api/sync/restore request is received, the API SHALL return the user's blueprint, preferences, all completion logs, and PR history
2. Each log SHALL be returned as an object with: id, exerciseId (canonical_name of the exercise), exerciseName (title), date, logType, sets (reconstructed from structured columns using the log_type as the mapping key), note, and optionally track, blockIndex, movementIndex (if stored)
3. Logs SHALL be returned as an array (not keyed by composite) — the front-end handles display mapping
4. ALL of the user's logs SHALL be returned (both sync-originated and web-UI-originated), regardless of source
5. PR history SHALL include current (non-superseded) personal_records grouped by exercise canonical_name, with pr_type, value, rep_count, and weight
6. When no data exists, return { status: "ok", blueprint: null, logs: [], preferences: null, prHistory: {} }

### Requirement 12: Exercise Name Resolution

**User Story:** As a developer, I want a service that resolves exercise names to Exercise records, so that incoming logs are correctly associated.

#### Acceptance Criteria

1. Resolution SHALL first try an exact match on exercises.canonical_name using a snake_case conversion of the input name
2. If no match, try a case-insensitive match on exercises.title
3. If no match, try a case-insensitive match on exercise_aliases.alias_name
4. If no match anywhere, auto-create a new Exercise with: title = input name, canonical_name = snake_case of input, exercise_type derived from the log_type
5. All lookups SHALL be scoped to global exercises (user_id IS NULL) and exercises owned by the authenticated user
6. Soft-deleted records SHALL be excluded from all lookups

### Requirement 13: Authentication & Authorization

**User Story:** As a developer, I want all data endpoints protected by Sanctum, so that users can only access their own data.

#### Acceptance Criteria

1. All endpoints except POST /register and POST /login SHALL require a valid Sanctum Bearer token
2. Invalid or missing tokens SHALL return HTTP 401
3. All data operations SHALL be scoped to the authenticated user
4. The X-Device-Id header SHALL be accepted and passed through on all authenticated requests

### Requirement 14: CORS Configuration

**User Story:** As a developer, I want the API to accept cross-origin requests, so that browser-based clients can communicate with it.

#### Acceptance Criteria

1. CORS SHALL allow all origins (wildcard) during the pilot phase
2. Allowed methods: GET, POST, DELETE, OPTIONS
3. Allowed headers: Authorization, Content-Type, X-Device-Id

### Requirement 15: Set Field Mapping by Log Type

**User Story:** As a developer, I want each log type's set fields correctly mapped to lift_set columns, so that data is queryable for PR detection while also preserving the original shape for lossless restore.

#### Acceptance Criteria

1. For "barbell", "single-dumbbell", "dual-dumbbell": map `weight` → lift_sets.weight, `reps` → lift_sets.reps
2. For "bodyweight", "added-weight": map `addedWeight` → lift_sets.weight, `reps` → lift_sets.reps
3. For "kettlebell": map `kbWeight` → lift_sets.weight, `reps` → lift_sets.reps
4. For "ball": map `ballWeight` → lift_sets.weight, `reps` → lift_sets.reps
5. For "static-hold": map `duration` → lift_sets.time
6. For "weighted-carry": map `weight` → lift_sets.weight, `duration` → lift_sets.time
7. For "dual-kettlebell": map `kbWeight` → lift_sets.weight, `duration` → lift_sets.time
8. For "cardio": map `distance` → lift_sets.distance, `distanceUnit` → lift_sets.distance_unit, `time` → lift_sets.time, `calories` → lift_sets.calories
9. For "cardio-calories": map `calories` → lift_sets.calories
10. For "cardio-distance": map `distance` → lift_sets.distance, `distanceUnit` → lift_sets.distance_unit, `time` → lift_sets.time
11. For "banded": map `bandColor` → lift_sets.band_color, `reps` → lift_sets.reps
12. For "bodyweight-reps": map `reps` → lift_sets.reps
13. ALL log types: the `log_type` stored on the lift_log serves as the mapping key for reconstructing the original set shape on restore (the SetFieldMapper is bidirectional — maps in on store, maps back out on restore)
14. The request-level `weight_unit` field (e.g., "lbs" or "kg") SHALL be stored as lift_sets.unit on every lift_set created for that log

### Requirement 16: Error Handling

**User Story:** As a developer, I want consistent error responses across all endpoints.

#### Acceptance Criteria

1. All responses SHALL be JSON with a `status` field ("ok" or "error")
2. Validation errors SHALL return HTTP 422 with { status: "error", message }
3. Auth errors SHALL return HTTP 401 with { status: "error", message }
4. Unexpected errors SHALL return HTTP 500 with { status: "error", message: "Internal server error" } — no internal details exposed
5. Rate limit errors SHALL return HTTP 429 with { status: "error", message: "Too many requests. Try again in X seconds." } and include a `Retry-After` header

### Requirement 17: Request Logging

**User Story:** As a developer, I want every incoming sync request logged durably with the full payload, so that if processing fails, the data can be recovered and replayed.

#### Acceptance Criteria

1. Every request to `/api/sync/*` (all endpoints) SHALL be logged to a filesystem log BEFORE any processing occurs
2. The log SHALL be stored at `storage/logs/sync/requests.log` (daily rotating) — in a dedicated subfolder, not mixed with the app's main logs
3. Each log entry SHALL be a JSON line containing: timestamp, IP, method, path, query params, all headers, and full request body
4. The logging SHALL NOT block request processing — if the log write fails, the request still proceeds
5. Log entries SHALL be retained via daily rotation (configurable retention period)
6. A Laravel CLI command `sync:purge-logs` SHALL exist to delete log files older than a configurable number of days (default 30)
7. A Laravel CLI command `sync:replay-failed` SHALL exist to parse the log file, identify requests that resulted in errors (by matching against response logs or by re-submitting and checking), and replay them through the controller logic
8. A documentation file SHALL be created at `docs/sync-api-operations.md` explaining: the logging setup, where logs live, how to find failed requests, how to replay them, and how to purge old logs

### Requirement 18: Rate Limiting

**User Story:** As a developer, I want the API protected from excessive requests, so that a misbehaving client or retry loop can't overwhelm the server.

#### Acceptance Criteria

1. A per-user throttle of 30 requests per minute SHALL be applied to all authenticated endpoints
2. A global application throttle of 60 requests per minute (across all users) SHALL be applied to all sync endpoints
3. When a request is throttled, the API SHALL return HTTP 429 with { status: "error", message: "Too many requests. Try again in X seconds." }
4. The 429 response SHALL include a `Retry-After` header with the number of seconds until the limit resets
5. The per-user limit takes precedence — a single user can't consume more than 5/minute even if the global limit hasn't been reached

### Requirement 19: Idempotency

**User Story:** As a developer, I want the API to handle duplicate requests safely, so that retry logic in the client doesn't create duplicate data.

#### Acceptance Criteria

1. The client MAY include an `X-Idempotency-Key` header (UUID) on POST /logs requests
2. If an idempotency key is provided and a log with that key already exists for the authenticated user, the API SHALL return the existing log's ID without creating a duplicate
3. If no idempotency key is provided, the API SHALL always create a new log (backward compatible)
4. Idempotency keys SHALL be scoped per user — two different users can use the same key without conflict
5. Idempotency key lookups SHALL use a column on the `lift_logs` table (`idempotency_key`, VARCHAR 36 nullable, indexed)

### Requirement 20: Request Size Validation

**User Story:** As a developer, I want the API to reject unreasonably large payloads, so that a bug in the client can't create thousands of set rows.

#### Acceptance Criteria

1. The `sets` array on POST /logs SHALL be limited to a maximum of 100 items
2. If the sets array exceeds 100 items, the API SHALL return HTTP 422 with a descriptive error
3. The overall request body size SHALL be limited to 1MB (enforced at the web server or middleware level)

### Requirement 21: Test Coverage for Cardio Migration

**User Story:** As a developer, I want test coverage for areas affected by the cardio distance migration, so that the migration doesn't silently break existing functionality.

#### Acceptance Criteria

1. Before the migration runs, a test SHALL verify that the `CardioProgressionChartGenerator` correctly reads distance data and produces chart output
2. A test SHALL verify end-to-end cardio logging through the web UI: create a cardio exercise, log via CreateLiftLogAction, verify the lift_set stores distance in the `distance` column (post-migration)
3. A test SHALL verify that `VolumeProgressionChartGenerator` returns 0/empty for cardio exercises (since reps will be null after migration)
4. All existing `CardioExerciseTypeTest` tests SHALL be updated to use the `distance` column instead of `reps`
5. All existing `CardioPRDetectionTest` tests SHALL be updated to create sets with `distance` instead of `reps`
6. A regression test SHALL verify that non-cardio exercises (regular, bodyweight, banded) are unaffected by the migration — their `reps` column remains intact



### Requirement 23: Exercise Auto-Creation Documentation

**User Story:** As a developer/operator, I want documentation about exercise auto-creation behavior, so that I know how to audit and clean up auto-created exercises.

#### Acceptance Criteria

1. The `docs/sync-api-operations.md` file SHALL include a section explaining exercise auto-creation: when it happens, what fields are populated, how to identify auto-created exercises
2. Auto-created exercises SHALL be identifiable by having `user_id = NULL` (global) and a `created_at` timestamp that matches a sync request (no explicit flag needed — the timestamp + lack of intelligence data is sufficient)
3. The documentation SHALL explain how to merge duplicate/typo exercises using the existing admin merge feature
