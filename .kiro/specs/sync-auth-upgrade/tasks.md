# Implementation Plan: Sync Auth Upgrade

## Overview

Upgrade the Logger Sync API authentication from username-based to email-based registration and login, adding Google and Apple social sign-in via JWT/JWKS verification. The implementation rewrites `AuthController`, adds two JWT verifier services, registers new routes, and adds an email existence check endpoint. All auth responses use a unified format. Laravel Sanctum remains for token management.

## Tasks

- [ ] 1. Set up configuration and JWT verifier services
  - [ ] 1.1 Add Apple service configuration to `config/services.php`
    - Add `'apple' => ['client_id' => env('APPLE_CLIENT_ID')]` to the services config array
    - Verify `services.google.client_id` config already exists (used by GoogleJwtVerifier)
    - _Requirements: 4.1_

  - [ ] 1.2 Create `app/Sync/Services/GoogleJwtVerifier.php`
    - Create class in `App\Sync\Services` namespace
    - Implement `verify(string $idToken): array` that fetches Google JWKS from `https://www.googleapis.com/oauth2/v3/certs`, caches for 24h
    - Decode JWT using `Firebase\JWT\JWT::decode()` and `Firebase\JWT\JWK::parseKeySet()`
    - Validate audience matches `config('services.google.client_id')`
    - Return `['email' => ..., 'name' => ..., 'sub' => ...]`
    - Throw `\UnexpectedValueException` on failure
    - _Requirements: 3.1, 3.2_

  - [ ] 1.3 Create `app/Sync/Services/AppleJwtVerifier.php`
    - Create class in `App\Sync\Services` namespace
    - Implement `verify(string $identityToken): array` that fetches Apple JWKS from `https://appleid.apple.com/auth/keys`, caches for 24h
    - Decode JWT using `Firebase\JWT\JWT::decode()` and `Firebase\JWT\JWK::parseKeySet()`
    - Validate audience matches `config('services.apple.client_id')`
    - Return `['email' => ..., 'name' => ..., 'sub' => ...]`
    - Throw `\UnexpectedValueException` on failure
    - _Requirements: 4.1, 4.2_

- [ ] 2. Rewrite AuthController core methods
  - [ ] 2.1 Add `authResponse()` private helper to `AuthController`
    - Create `authResponse(User $user, string $deviceId): JsonResponse`
    - Generate Sanctum token: `$user->createToken($deviceId)->plainTextToken`
    - Return JSON `{ status: "ok", token, athlete: $user->name, email: $user->email }`
    - _Requirements: 6.1_

  - [ ] 2.2 Rewrite `register()` method in `AuthController`
    - Validate `email` (required|email|unique:users,email), `password` (required|string|min:6), `name` (required|string), `device_id` (required|string)
    - Create user with name, email, hashed password
    - Return via `authResponse()` helper
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

  - [ ] 2.3 Rewrite `login()` method in `AuthController`
    - Validate `email` (required|email), `password` (required|string), `device_id` (required|string)
    - Look up user by `users.email`
    - If `app()->environment('local')`: skip password hash verification (accept any password for any existing user)
    - Otherwise: verify password hash, throw `AuthenticationException('Invalid credentials.')` on failure
    - Return via `authResponse()` helper
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7_

- [ ] 3. Implement social auth methods
  - [ ] 3.1 Add `findOrCreateSocialUser()` private helper to `AuthController`
    - Create `findOrCreateSocialUser(string $email, string $name, ?string $googleId): User`
    - Try `User::where('email', $email)->first()` — if found, update `google_id` if provided and not already set, return user
    - If not found, create new user with email, name, and google_id
    - _Requirements: 3.3, 3.4, 3.9, 4.3, 4.4_

  - [ ] 3.2 Add `googleAuth()` method to `AuthController`
    - Inject `GoogleJwtVerifier` via method injection
    - Validate `id_token` (required|string) and `device_id` (required|string)
    - Call `$verifier->verify()`, catch exceptions and return 401 with `{ status: "error", message: "Invalid Google token." }`
    - Call `findOrCreateSocialUser()` with extracted email, name, and sub
    - Return via `authResponse()`
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9_

  - [ ] 3.3 Add `appleAuth()` method to `AuthController`
    - Inject `AppleJwtVerifier` via method injection
    - Validate `identity_token` (required|string), `authorization_code` (required|string), `device_id` (required|string)
    - Call `$verifier->verify()`, catch exceptions and return 401 with `{ status: "error", message: "Invalid Apple token." }`
    - Call `findOrCreateSocialUser()` with extracted email, name (fallback to email prefix), and null googleId
    - Return via `authResponse()`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9_

- [ ] 4. Add routes and email check endpoint
  - [ ] 4.1 Register new routes in `routes/sync.php`
    - Add `Route::post('/auth/google', [AuthController::class, 'googleAuth'])`
    - Add `Route::post('/auth/apple', [AuthController::class, 'appleAuth'])`
    - Add `Route::post('/auth/check', [AuthController::class, 'checkEmail'])` (no auth middleware)
    - _Requirements: 3.1, 4.1, 5.1, 5.5_

  - [ ] 4.2 Implement `checkEmail()` method in `AuthController`
    - Validate `email` (required|email)
    - Look up user by email
    - If exists: return `{ status: "ok", exists: true, has_password: <bool>, has_google: <bool> }`
    - If not: return `{ status: "ok", exists: false }`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [ ] 5. Checkpoint - Verify implementation
  - Ensure all code compiles without errors, ask the user if questions arise.

- [ ] 6. Write feature tests
  - [ ]* 6.1 Write feature tests for email registration
    - Test successful registration → 200 with `{ status, token, athlete, email }`
    - Test duplicate email → 422
    - Test invalid email format → 422
    - Test password too short → 422
    - Test missing required fields → 422
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

  - [ ]* 6.2 Write feature tests for email login
    - Test successful login → 200 with correct response shape
    - Test wrong password → 401 with `{ status: "error", message: "Invalid credentials." }`
    - Test nonexistent email → 401
    - Test missing fields → 422
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [ ]* 6.3 Write feature tests for Google auth
    - Mock `GoogleJwtVerifier` to return payload
    - Test creates new user → 200 with correct response
    - Test existing user by email → 200, reuses user, updates google_id
    - Test invalid token (mock throws) → 401
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.9_

  - [ ]* 6.4 Write feature tests for Apple auth
    - Mock `AppleJwtVerifier` to return payload
    - Test creates new user → 200 with correct response
    - Test existing user by email → 200, reuses user
    - Test name fallback to email prefix when name is null
    - Test invalid token → 401
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 6.5 Write feature tests for email check endpoint
    - Test existing user with password and google_id → returns correct flags
    - Test nonexistent email → `{ exists: false }`
    - Test invalid email format → 422
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

- [ ] 7. Exercise resolution upgrades and merge alias fix
  - [ ] 7.1 Update `ExerciseResolverService` to accept `canonical_name` field and scope to global-only
    - Modify `resolve()` to accept optional `canonicalName` parameter
    - When provided, use it directly for step 1 (exact canonical_name match) instead of `Str::snake(exerciseName)`
    - Use `exercise_name` for steps 2-3 (title/alias fallback)
    - When auto-creating, use `canonicalName` for `exercises.canonical_name` and `exerciseName` for `exercises.title`
    - Change all lookups to scope to global exercises only (`WHERE user_id IS NULL`) — remove the `orWhere('user_id', $user->id)` clause
    - Change alias lookup to scope to global aliases only (`exercise_aliases.user_id IS NULL`)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ] 7.2 Update `LogController` to pass `canonical_name` to resolver
    - Accept optional `canonical_name` field in POST /logs validation
    - Pass it to `StoreSyncLogAction` → `ExerciseResolverService`
    - _Requirements: 7.4, 7.5_

  - [ ] 7.3 Update `ExerciseMergeService` to store canonical_name alias
    - In `mergeExercises()`, after creating the title-based alias, also create a global alias with `alias_name = source.canonical_name`
    - Skip if `canonical_name` equals `Str::slug(title, '_')` (would be redundant)
    - Skip if alias already exists (avoid duplicate error)
    - Set `user_id = NULL` on the canonical alias (global, not per-user)
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

  - [ ] 7.4 Update `docs/sync-api-operations.md` with new exercise resolution logic
    - Document that POST /logs now accepts optional `canonical_name` field alongside `exercise_name`
    - Update the "Matching Priority" section to explain: canonical_name is used for step 1 when provided
    - Update the "Merging Duplicates" section to explain: merge now stores both the title AND the canonical_name as aliases, preventing future duplicate creation from the same client slug
    - Add a note explaining the workflow: duplicate auto-created → admin merges → canonical_name alias created → future requests resolve correctly
    - _Requirements: 8.1, 8.6_

- [ ] 8. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- No database migration needed — existing `users` table already has `email`, `name`, `password`, and `google_id` columns
- `firebase/php-jwt` package is already available in vendor
- Checkpoints ensure incremental validation
- No backward compatibility with username-based login (old flow removed entirely)
- No email migration logic — users with `@sync.local` emails must re-register

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["2.2", "2.3", "3.1"] },
    { "id": 3, "tasks": ["3.2", "3.3"] },
    { "id": 4, "tasks": ["4.1", "4.2"] },
    { "id": 5, "tasks": ["6.1", "6.2", "6.3", "6.4", "6.5"] },
    { "id": 6, "tasks": ["7.1", "7.2", "7.3", "7.4"] }
  ]
}
```
