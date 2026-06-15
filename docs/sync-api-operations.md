# Sync API Operations Guide

This document outlines the operations, observability, and administrative actions for the Squirby Sync API.

## Observability & Request Logging

Every request sent to the `/api/sync/*` endpoints is logged prior to any processing. This serves as a safety net to ensure that client data is durably captured on the server, even if downstream application logic encounters an error.

### Log File Location
Logs are written to daily rotating files located at:
```
storage/logs/sync/requests-YYYY-MM-DD.log
```

### Log Format
Each line in the log file is a single JSON object containing the following structure:
```json
{
  "ts": "2026-06-15T03:00:00-07:00",
  "ip": "127.0.0.1",
  "method": "POST",
  "path": "api/sync/logs",
  "query": {},
  "headers": {
    "authorization": ["Bearer ..."],
    "x-device-id": ["UUID"],
    "content-type": ["application/json"]
  },
  "body": {
    "exercise_name": "Squat",
    "date": "2026-06-15",
    "log_type": "barbell",
    "weight_unit": "lbs",
    "sets": [{"weight": 135, "reps": 5}]
  }
}
```

### Finding Failed Requests
Since the request logging is pre-processing and does not record the response, failed requests must be identified using one of the following methods:
1. Auditing the main application logs (`storage/logs/laravel.log`) for exceptions referencing `/api/sync/*` routes.
2. Checking client-side logs for HTTP status codes >= 400.
3. Re-submitting logged requests through the replay command (e.g. via dry-run) to inspect response status codes.

---

## Replaying Requests

If a request failed or needs to be audited, it can be replayed using the `sync:replay-failed` Artisan command. This command parses the sync requests log, filters according to criteria, and dispatches them through the application's HTTP kernel without making real network requests.

### Usage
```bash
php artisan sync:replay-failed [options]
```

### Options
- `--days=N`: Scan logs from the last `N` days (default: `1`).
- `--endpoint=substring`: Filter requests matching the path (e.g., `--endpoint=logs`).
- `--user=identifier`: Filter requests by User ID or username.
- `--dry-run`: Preview matching requests without executing them.

### Examples
To preview all requests from today:
```bash
php artisan sync:replay-failed --dry-run
```

To replay requests from the last 3 days for a specific user:
```bash
php artisan sync:replay-failed --days=3 --user=john_doe
```

---

## Purging Old Logs

To prevent log files from consuming excessive disk space, a purging command is provided to clean up logs older than a specified age.

### Usage
```bash
php artisan sync:purge-logs [options]
```

### Options
- `--days=N`: Delete sync log files older than `N` days (default: `30`).

### Example
To purge logs older than 14 days:
```bash
php artisan sync:purge-logs --days=14
```

---

## Exercise Auto-Creation

When a client submits a completion log, the API's **Exercise Resolver** will resolve it to a global exercise. If the exercise does not exist, a new global exercise is automatically created.

### Optional canonical_name Field
Clients can send an optional `canonical_name` field (e.g. direct client slug) alongside the required `exercise_name` field in `POST /api/sync/logs`. When provided, this slug is used directly for the canonical name lookup.

### Matching Priority
1. Exact match on `exercises.canonical_name` (using `canonical_name` if provided, otherwise falling back to `Str::snake(exercise_name)`).
2. Case-insensitive match on `exercises.title` (using `exercise_name`).
3. Case-insensitive match on global `exercise_aliases.alias_name` (using `exercise_name`, restricted to global aliases with `user_id = NULL`).
4. Auto-creation (using `canonical_name` for the canonical name and `exercise_name` for the title).

### Identification
Auto-created exercises are created as global exercises:
- `user_id = NULL`
- `show_in_feed = true`
- They have a `created_at` timestamp corresponding to a sync request and lack metadata in the `exercise_intelligence` table.

### Merging Duplicates
If a user syncs an exercise with a typo or slight variation, it may result in a duplicate exercise. Administrators can resolve this by using the admin exercise merge feature to merge the auto-created exercise into the canonical global exercise.

When a merge is performed, the system automatically creates a global alias (`user_id = NULL`) on the target exercise with the source exercise's `canonical_name` (unless it is redundant with the title or already exists). This prevents future duplicate creation from the same client slug.

**Workflow**:
1. **Duplicate Auto-Created**: Client sends a sync request with `canonical_name` = `'benchpress'` and `exercise_name` = `'Benchpress'`, creating a duplicate exercise.
2. **Admin Merge**: An administrator merges `'Benchpress'` into the canonical global exercise `'Barbell Bench Press'`.
3. **Alias Created**: A global alias with `alias_name` = `'benchpress'` is automatically created on `'Barbell Bench Press'`.
4. **Future Resolution**: Future sync requests with `canonical_name` = `'benchpress'` resolve directly to `'Barbell Bench Press'` without creating duplicates.

---

## Rate Limiting

The API is protected by a two-tier rate limiter to prevent clients or network retry loops from overwhelming the database.

### Limits
1. **Per-user limit**: 30 requests per minute.
2. **Global limit**: 60 requests per minute across all users.

### Throttled Response (HTTP 429)
When a client exceeds either limit, the API returns:
- Status Code: `429 Too Many Requests`
- Body:
  ```json
  {
    "status": "error",
    "message": "Too many requests. Try again in X seconds."
  }
  ```
- Headers:
  - `Retry-After`: Number of seconds the client must wait before retrying.

---

## Idempotency Key Usage

To handle client retries safely without generating duplicate logs, the `POST /api/sync/logs` endpoint supports idempotency keys.

### Usage
1. The client should generate a unique UUID for each completion log and send it in either:
   - The `X-Idempotency-Key` HTTP header.
   - The `idempotency_key` field in the request body.
2. When the key is received, the API checks for an existing log for that user matching the key.
3. If a match is found, the API returns the existing log's ID with a `200 OK` status and skips database writes.
4. If no key is provided, a new log is created every time.
