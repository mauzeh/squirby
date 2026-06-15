# Requirements Document

## Introduction

This spec upgrades the Logger Sync API authentication system from username-based to email-based registration and login, and adds Google and Apple social sign-in endpoints. Existing athletes with `@sync.local` email addresses must re-register with their real email (via email+password or social auth) — the old username-based flow is removed entirely. All auth endpoints return a unified response format including email. Laravel Sanctum token auth remains unchanged for downstream sync endpoints.

## Glossary

- **Sync_Auth**: The authentication subsystem within `app/Sync/Controllers/AuthController.php` handling registration, login, and social sign-in for the mobile sync API.
- **Social_Auth**: Authentication via Google or Apple identity tokens, verified against the provider's public keys, used to create-or-find a user by email.
- **Sanctum_Token**: A Laravel Sanctum personal access token returned on successful auth, used as Bearer token for all subsequent sync API requests.
- **Device_ID**: A client-provided identifier representing the device making the request, stored as the token name in Sanctum.

## Requirements

### Requirement 1: Email-Based Registration

**User Story:** As a new athlete, I want to register with my real email address and a display name, so that my account uses a real email for future social sign-in linking.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/register` request is received with `{ email, password, name, device_id }`, the API SHALL create a new user with `users.email` = email, `users.name` = name, `users.password` = hashed password
2. WHEN registration succeeds, the API SHALL generate a Sanctum personal access token named with the device_id and return `{ status: "ok", token, athlete: users.name, email: users.email }`
3. The `email` field SHALL be validated as a valid email format and unique in the `users` table
4. The `password` field SHALL be validated with a minimum length of 6 characters
5. The `name` field SHALL be required and be a non-empty string
6. The `device_id` field SHALL be required and be a non-empty string
7. WHEN the email already exists in the `users` table, the API SHALL return HTTP 422 with a validation error message
8. WHEN any validation fails, the API SHALL return HTTP 422 with the specific validation error messages

### Requirement 2: Email-Based Login

**User Story:** As a returning athlete, I want to log in with my email and password, so that I can obtain a Sanctum token for syncing.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/login` request is received with `{ email, password, device_id }`, the API SHALL look up the user by `users.email` and verify the password hash
2. WHEN credentials are valid, the API SHALL generate a Sanctum token named with device_id and return `{ status: "ok", token, athlete: users.name, email: users.email }`
3. WHEN no user is found with the given email OR the password does not match, the API SHALL return HTTP 401 with `{ status: "error", message: "Invalid credentials." }`
4. The `email` field SHALL be required and be a valid email format
5. The `password` field SHALL be required
6. The `device_id` field SHALL be required and be a non-empty string

### Requirement 3: Google Social Auth

**User Story:** As an athlete, I want to sign in with my Google account, so that I can authenticate without remembering a separate password.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/auth/google` request is received with `{ id_token, device_id }`, the API SHALL verify the Google JWT against Google's public keys
2. WHEN the id_token is valid, the API SHALL extract the email and name from the token claims
3. IF a user exists with the extracted email, the API SHALL use that existing user
4. IF no user exists with the extracted email, the API SHALL create a new user with `users.email` = extracted email, `users.name` = extracted name, `users.google_id` = Google subject ID
5. WHEN user is found or created, the API SHALL generate a Sanctum token named with device_id and return `{ status: "ok", token, athlete: users.name, email: users.email }`
6. WHEN the id_token is invalid or verification fails, the API SHALL return HTTP 401 with `{ status: "error", message: "Invalid Google token." }`
7. The `id_token` field SHALL be required
8. The `device_id` field SHALL be required and be a non-empty string
9. IF a user is found by email, the API SHALL update `users.google_id` with the Google subject ID if it is not already set

### Requirement 4: Apple Social Auth

**User Story:** As an athlete, I want to sign in with my Apple account, so that I can authenticate without remembering a separate password.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/auth/apple` request is received with `{ identity_token, authorization_code, device_id }`, the API SHALL verify the Apple identity token JWT against Apple's public keys
2. WHEN the identity_token is valid, the API SHALL extract the email and name from the token claims
3. IF a user exists with the extracted email, the API SHALL use that existing user
4. IF no user exists with the extracted email, the API SHALL create a new user with `users.email` = extracted email, `users.name` = extracted name (or email prefix if name not provided by Apple)
5. WHEN user is found or created, the API SHALL generate a Sanctum token named with device_id and return `{ status: "ok", token, athlete: users.name, email: users.email }`
6. WHEN the identity_token is invalid or verification fails, the API SHALL return HTTP 401 with `{ status: "error", message: "Invalid Apple token." }`
7. The `identity_token` field SHALL be required
8. The `authorization_code` field SHALL be required
9. The `device_id` field SHALL be required and be a non-empty string

### Requirement 5: Email Existence Check

**User Story:** As the Athlete app, I want to check whether an email already has an account, so that I can present the correct next step (login vs registration) without confusion.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/auth/check` request is received with `{ email }`, the API SHALL look up whether a user exists with that email in the `users` table
2. IF the user exists, the API SHALL return `{ status: "ok", exists: true, has_password: <boolean>, has_google: <boolean> }` where `has_password` indicates whether the user has a non-null password and `has_google` indicates whether `google_id` is set
3. IF the user does not exist, the API SHALL return `{ status: "ok", exists: false }`
4. The `email` field SHALL be required and be a valid email format
5. The endpoint SHALL NOT require authentication (it is called before the user is signed in)
6. The endpoint SHALL NOT reveal sensitive information beyond existence and available auth methods

### Requirement 6: Unified Response Format

**User Story:** As an app developer, I want all auth endpoints to return the same response shape, so that client code can handle any auth method uniformly.

#### Acceptance Criteria

1. ALL successful auth responses (register, login, Google auth, Apple auth) SHALL return HTTP 200 with the JSON body `{ status: "ok", token: "<sanctum_token>", athlete: "<users.name>", email: "<users.email>" }`
2. ALL authentication failure responses SHALL return HTTP 401 with `{ status: "error", message: "<description>" }`
3. ALL validation failure responses SHALL return HTTP 422 with Laravel's standard validation error format


### Requirement 7: Exercise Resolution — Accept canonical_name

**User Story:** As a developer, I want the POST /logs endpoint to accept a `canonical_name` field alongside `exercise_name`, so that exercise matching uses the exact slug and avoids creating duplicates.

#### Acceptance Criteria

1. WHEN a POST `/api/sync/logs` request includes a `canonical_name` field, the ExerciseResolverService SHALL use it directly for step 1 (exact match on `exercises.canonical_name`) instead of deriving it via `Str::snake(exercise_name)`
2. WHEN a POST `/api/sync/logs` request includes both `canonical_name` and `exercise_name`, the resolver SHALL use `canonical_name` for step 1 (canonical match) and `exercise_name` for steps 2-3 (title/alias fallback)
3. WHEN auto-creating an exercise (step 4), the resolver SHALL use `canonical_name` as the `exercises.canonical_name` value and `exercise_name` as the `exercises.title` value
4. The `canonical_name` field SHALL be optional — if omitted, the existing behavior (derive from `Str::snake(exercise_name)`) SHALL continue to work
5. The `exercise_name` field SHALL remain required for backward compatibility with existing sync clients
6. WHEN the resolver matches an existing exercise (via canonical_name, title, or alias), IF the matched exercise's `title` differs from the provided `exercise_name`, the resolver SHALL update `exercises.title` to match the `exercise_name` value — the Athlete app is the source of truth for display names

### Requirement 8: Merge Operation — Store canonical_name as alias

**User Story:** As an admin, I want the merge operation to store the source exercise's canonical_name as an alias on the target, so that future sync requests with the old canonical_name resolve correctly instead of creating duplicates.

#### Acceptance Criteria

1. WHEN an exercise merge is performed, the `ExerciseMergeService` SHALL create an additional alias on the target exercise with `alias_name` = the source exercise's `canonical_name` (in addition to the existing title-based alias)
2. IF the source exercise's `canonical_name` is different from its `title`, the merge SHALL create two aliases: one for the title (existing behavior) and one for the canonical_name (new behavior)
3. IF the source exercise's `canonical_name` is identical to a snake_case version of its title, only one alias SHALL be created to avoid redundancy
4. The canonical_name alias SHALL be created as a global alias (`user_id = NULL`) so that it resolves for all users, not just the source exercise's owner
5. IF an alias with the same `alias_name` already exists for the target exercise, the merge SHALL skip creating the duplicate without failing
6. The merge operation SHALL remain an admin-only manual action — it is never invoked by the Sync API

### Requirement 9: Exercise Resolution — Global-Only Scope

**User Story:** As a developer, I want the Sync API's exercise resolver to only match against global exercises, so that user-owned exercises in Logger never interfere with sync resolution and all synced logs are consistently attached to global exercises.

#### Acceptance Criteria

1. The `ExerciseResolverService` SHALL scope all lookups (canonical_name match, title match, alias match) to global exercises only (`WHERE user_id IS NULL`)
2. The resolver SHALL NOT match against user-owned exercises (`user_id IS NOT NULL`) during any step of the resolution chain
3. WHEN auto-creating an exercise, the resolver SHALL create it as a global exercise (`user_id = NULL`) — this is existing behavior and SHALL be preserved
4. The alias lookup (step 3) SHALL only match global aliases (`exercise_aliases.user_id IS NULL`) to avoid per-user aliases affecting sync resolution for other users
5. This scoping applies exclusively to the Sync API's `ExerciseResolverService` — the web UI's exercise lookup behavior is unaffected
6. IF the resolver attempts to auto-create an exercise but the `canonical_name` is already taken by a user-owned exercise, the resolver SHALL promote that user-owned exercise to global (`user_id = NULL`) and use it instead of creating a new exercise
7. Promotion SHALL only occur when the existing exercise's `canonical_name` exactly matches the provided `canonical_name` — it SHALL NOT promote based on title or alias matches
8. After promotion, the exercise SHALL be visible to all users and usable for all future sync resolution
