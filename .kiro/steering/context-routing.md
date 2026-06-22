# Context Routing

When the user's request matches one of these patterns, immediately activate the listed steering files (via `#` context or by reading them directly) before starting work.

## Sync API Work

**Trigger patterns:** User asks to modify sync endpoints, mentions StoreSyncLogAction, SetFieldMapper, ExerciseResolverService, sync auth, pull/push sync, or works on files in `app/Sync/`.

**Activate:**
- `sync-api-context.md` — project context, endpoint inventory, design constraints


## Exercise Type / Log Type Changes

**Trigger patterns:** User modifies `SetFieldMapper.php`, `ExerciseResolverService.php` (especially `deriveExerciseType`), adds a new exercise type strategy in `app/Services/ExerciseTypes/`, changes `config/exercise_types.php`, or mentions exercise type mapping, log type derivation, or field mapping between Athlete and Logger.

**Activate:**
- Read `../../.kiro/steering/cross-app-contracts.md` (root-level) — explains the contract test infrastructure
- After making changes, run `npx vitest --run contracts/__tests__/` from the project root to verify cross-app consistency
- If adding a new logType or changing field mapping: you MUST also update `../../contracts/fixtures/log-type-mapping.json` and the PHP runner at `../../contracts/runners/logger-mapping.php` if the interface changed
