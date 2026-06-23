# Sync Auth Upgrade — Prompt for Antigravity CLI

## Before You Start

Read these files in order. They contain everything you need to implement this feature correctly.

### 1. Steering (project rules — always follow)

```
.kiro/steering/git-workflow.md          → commit freely, NEVER push, NEVER merge into main
.kiro/steering/safe-operations.md       → files to never touch, bash safety, artisan safety
.kiro/steering/project-conventions.md   → architectural principles for this project
.kiro/steering/laravel-boost.md         → Laravel framework conventions (PHP style, testing, Pint)
```

### 2. Feature Spec (what to build)

```
.kiro/specs/sync-auth-upgrade/requirements.md → requirements (9 requirements with acceptance criteria)
.kiro/specs/sync-auth-upgrade/design.md       → architecture, method designs, JWT verifier services
.kiro/specs/sync-auth-upgrade/tasks.md        → ordered implementation plan with dependencies
```

### 3. Existing Code to Understand (read before modifying)

```
app/Sync/Controllers/AuthController.php → the controller you're rewriting (currently username-based)
app/Sync/Services/ExerciseResolverService.php → exercise resolution you're modifying (add canonical_name, global-only scope)
app/Services/ExerciseMergeService.php   → merge service you're modifying (add canonical_name alias)
app/Services/ExerciseAliasService.php   → alias creation service (used by merge)
app/Models/Exercise.php                 → exercise model (canonical_name, user_id, aliases relationship)
app/Models/ExerciseAlias.php            → alias model (alias_name, exercise_id, user_id)
app/Models/User.php                     → user model (email, name, password, google_id, HasApiTokens)
routes/sync.php                         → existing sync routes (add new auth routes here)
config/services.php                     → add Apple service config here
```

### 4. Reference (already implemented — don't rebuild, just understand)

```
.kiro/specs/squirby-sync-api/design.md  → original sync API design (set field mapping table, exercise type derivation)
app/Sync/Services/SetFieldMapper.php    → bidirectional set field mapping (already implemented)
app/Sync/Actions/StoreSyncLogAction.php → how logs are stored (passes exercise resolver result)
```

## Execution Plan

Follow the tasks in `.kiro/specs/sync-auth-upgrade/tasks.md` in order. Each numbered section is a phase.

### Phase order:

1. **Configuration** (task 1.1) — Apple service config
2. **JWT Verifiers** (tasks 1.2–1.3) — GoogleJwtVerifier, AppleJwtVerifier
3. **AuthController rewrite** (tasks 2.1–2.3, 3.1–3.3) — authResponse helper, register, login, findOrCreateSocialUser, googleAuth, appleAuth
4. **Routes & email check** (tasks 4.1–4.2) — register new routes, checkEmail endpoint
5. **Checkpoint** — run `php artisan test --parallel` to verify nothing broke
6. **Exercise resolver upgrade** (task 7.1–7.2) — accept canonical_name, global-only scope, promote user-owned on conflict, update title from exercise_name
7. **Merge service upgrade** (task 7.3) — store canonical_name as global alias on merge
8. **Documentation** (task 7.4) — update docs/sync-api-operations.md
9. **Tests** (tasks 6.1–6.5) — feature tests for all auth endpoints
10. **Final checkpoint** — run `php artisan test --parallel`

### HARD RULES — NEVER VIOLATE THESE:

- **NEVER commit.** Do not run `git commit`, `git add`, or any git command. The user handles all version control.
- **NEVER run Pint.** Do not run `vendor/bin/pint` in any form. It reformats the entire codebase and creates massive noise. Write clean code yourself — follow the style of sibling files.
- **NEVER push.** Do not run `git push` under any circumstances.
- **NEVER run destructive database commands.** No `migrate:fresh`, `migrate:reset`, `db:wipe`.

### Implementation rules:

- **All new sync code goes in `app/Sync/`** — JWT verifiers go in `app/Sync/Services/`. Don't scatter sync code into `app/Http/` or `app/Services/`.
- **Exception: ExerciseMergeService stays where it is** (`app/Services/ExerciseMergeService.php`) — you're modifying it, not moving it.
- **Routes go in `routes/sync.php`** — add new routes alongside existing ones.
- **Always use `php artisan test --parallel` when running tests.** This project has 2000+ tests; parallel execution takes ~11 seconds vs 100+ sequential.
- **To run a specific test file:** `php artisan test --parallel tests/Feature/Sync/SomeTest.php`
- **To filter by name:** `php artisan test --parallel --filter=testName`
- **Never use `DB::` facade** — use Eloquent models and relationships.
- **Use PHP 8 constructor promotion** in all new classes.
- **Add explicit return types** to all methods.
- **Use `php artisan make:test --phpunit`** to generate test files.
- **The ExerciseResolverService change (global-only scope) is the riskiest part** — it changes how exercises are matched. Run the full test suite after this phase. If tests fail, fix them before proceeding.

### Key context:

- **Old `@sync.local` users are orphaned.** The existing 15 athletes have accounts with emails like `kristie@sync.local`. These are dead rows — do NOT try to migrate, link, or clean them up. They simply won't match any new logins. New accounts will be created with real emails.
- **`firebase/php-jwt` is already in vendor** — use `Firebase\JWT\JWT` and `Firebase\JWT\JWK` for JWT decoding. No need to install anything.
- **Google JWKS URL:** `https://www.googleapis.com/oauth2/v3/certs`
- **Apple JWKS URL:** `https://appleid.apple.com/auth/keys`
- **Cache JWKS keys for 24 hours** using Laravel's cache system.
- **Exercise resolution must be global-only** — the resolver must ONLY match exercises where `user_id IS NULL`. If a canonical_name is taken by a user-owned exercise, promote it to global.
- **Athlete app is the source of truth for exercise names** — when the resolver matches an exercise and the title differs from `exercise_name`, update the title.

### What success looks like:

1. `php artisan test --parallel` passes (all existing + new tests)
2. `php artisan route:list --path=api/sync` shows the new routes: auth/google, auth/apple, auth/check
3. POST /api/sync/register accepts `{ email, password, name, device_id }` and returns `{ status, token, athlete, email }`
4. POST /api/sync/login accepts `{ email, password, device_id }` and returns `{ status, token, athlete, email }`
5. POST /api/sync/auth/google verifies a Google id_token and returns a Sanctum token
6. POST /api/sync/auth/apple verifies an Apple identity_token and returns a Sanctum token
7. POST /api/sync/auth/check returns `{ exists, has_password, has_google }` for an email
8. POST /api/sync/logs accepts optional `canonical_name` field — used for direct slug matching
9. ExerciseResolverService scopes all lookups to global exercises only
10. ExerciseMergeService creates a global canonical_name alias when merging
11. No existing web UI functionality is broken
12. No existing sync API tests are broken
