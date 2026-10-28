# Interference-Dismissal Sync (Logger slice)

**Status:** plan (ready for prompt). **Pairing:** Prism ↔ Logger. **Slice:** Logger.
**Design-of-record:** `squirby/docs/sync/prism-logger/design.md` (root). **Problem statement:**
`squirby/docs/sync/prism-logger/interference-resolution-sync-problem.md` (root).

> This is the **Logger slice** of a cross-app feature. It stays entirely inside `logger/`. The cross-app
> shapes it must agree with are duplicated INLINE below (the dismissal wire shape, the `/changes` delta
> key); this slice does NOT read the root design doc or the Prism repo at author time. The root
> `contracts/` reconciliation is authored LAST, after this slice and the Prism slice both exist.

---

## Context

Prism (the programming studio) lets a coach **dismiss** an interference warning — a flag that two adjacent
workout days repeat a movement pattern/muscle group. Today a dismissal lives only in one browser's
localStorage and never leaves the device. Logger already is Prism's durable, gym-partitioned sync backend
(programs, program cells, exercise overlay, preferences). This slice adds a **sixth kind of gym-scoped
state** — interference dismissals — so a dismissal syncs across a gym's devices and survives a cold-start
rehydrate, exactly like the exercise overlay does.

Logger's role is unchanged and deliberately dumb: **store opaque rows and range-scan them.** It performs no
interference computation and never parses the dismissal key.

## Problem Statement

There is no server-side home for a dismissal. This slice provides one: a granular, gym-scoped table plus a
write endpoint and a delta-pull extension, both modeled one-to-one on the existing `prism_exercise_overlay`
table and its upsert/tombstone action.

## The dismissal — what Logger stores (opaque to the server)

A dismissal is identified by a **resolution key string** supplied by the client. Logger treats it as an
opaque string; it never parses it. (For context only — Logger must NOT depend on this shape: the key is
`<programIdentity>|<sortedPair>|<sortedItems>`, e.g. `3f2a…-uuid|w1d1~w1d2|hinge,squat`. The client owns
the key's internal structure and any identity translation inside it.)

State is presence-based: a **row present with `deleted_at = null` means dismissed**; a **tombstoned row
(`deleted_at` set) means un-dismissed (reset)**. A missing row is "never seen," which is why an un-dismiss
must be an explicit tombstone, not a deleted row — a missing row in a delta is ambiguous.

## Architecture

Copy the `prism_exercise_overlay` pattern exactly. That table is the canonical granular + soft-delete
tombstone precedent in this codebase, and dismissals have the identical shape (a per-gym bag of
independently-addressable rows, each either live or tombstoned).

### New table — `prism_interference_dismissals`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `gym_id` | string FK → `gyms.id`, `cascadeOnDelete` | partition key |
| `resolution_key` | string | the opaque client key; part of the unique index |
| `device_id` | string(36) nullable | carried like other prism rows |
| `deleted_at` | `timestamp(6)` nullable | tombstone = un-dismissed |
| `created_at` / `updated_at` | `timestamps(6)` | server-stamped microsecond precision |

- **Unique index** `[gym_id, resolution_key]` — the upsert key.
- **Index** `[gym_id, updated_at]` — the delta range scan.
- `resolution_key` length: use a generous `string` (e.g. 512) so a long sorted-items key fits; it is NOT an
  FK (the program identity inside it is Prism-local).

### New model — `App\Sync\Models\PrismInterferenceDismissal`

Mirror `PrismExerciseOverlayRecord`: `$table`, `$guarded = []` (or explicit `$fillable`), `$casts` for the
`timestamp(6)` columns, and whatever `protected $dateFormat`/precision convention the overlay model uses so
microsecond timestamps round-trip.

### New action — `App\Sync\Actions\UpsertPrismInterferenceDismissalsAction`

A one-to-one copy of `UpsertPrismExercisesAction`:
- Signature: `execute(string $gymId, array $dismissals, array $removals, ?string $deviceId): Carbon`
  - `$dismissals` — `[{ resolution_key: string }]` → upsert rows with `deleted_at = null`.
  - `$removals` — `[{ resolution_key: string }]` → second `upsert()` writing `deleted_at = now (Y-m-d H:i:s.u)`.
- Both `upsert()` calls key on `['gym_id', 'resolution_key']`, update `['device_id', 'deleted_at']`.
- Returns the max of `updated_at`/`deleted_at` across touched rows (same reduction the overlay action uses).
- Early-return `Carbon::now()` when both arrays are empty.

> **Reset-all note:** Prism's "reset dismissed warnings" (per program) clears N keys at once. The client
> enumerates those N keys and sends them as one request's `removals[]` array — exactly as cell removals are
> batched. So this action's `$removals` loop must handle an N-element array in a single call. No
> "delete-whole-scope" verb exists or is needed; the batched tombstone array is the mechanism.

### New controller method + route

- Controller `App\Sync\Controllers\PrismInterferenceDismissalController` (or a method on an existing Prism
  controller — follow whatever granularity the other prism write controllers use; the overlay has its own
  `PrismExerciseController`, so a dedicated controller is consistent).
- Method `store(Request/FormRequest, string $gym)`:
  - `findGymOrFail($gym)` via the `ValidatesGym` trait.
  - Pull `dismissals` + `removals` from a FormRequest (`App\Sync\Requests\StorePrismInterferenceDismissalsRequest`).
  - Call the action, return `{ status: 'ok', updated_at: <iso8601> }` (match the overlay/cells response).
- Route in `routes/sync.php`, in the **`throttle:prism-write`** group alongside the other prism writes:
  ```php
  Route::post('/prism/{gym}/interference-dismissals', [PrismInterferenceDismissalController::class, 'store']);
  ```
  Full path (prefix `api/sync`): `POST /api/sync/prism/{gym}/interference-dismissals`.

### `/changes` delta extension — additive sixth section

`PrismChangesController@index` is a hand-rolled enumeration of five sections + a cursor high-water-mark.
Add a **sixth section**, copying the exercises block verbatim in structure:

1. Query `PrismInterferenceDismissal::where('gym_id', $gym)`, conditionally `->where('updated_at', '>=', $since)`.
2. Map each row to `{ resolution_key, deleted_at: <iso8601|null>, updated_at: <iso8601> }`.
3. Fold its `max('updated_at')` into the `$updatedInstants` array and its `max('deleted_at')` (where
   `whereNotNull('deleted_at')`) into the `$deletedInstants` array so the one-cursor-per-gym HWM advances
   when a dismissal changes. **This fold is mandatory** — if omitted, a dismissal-only change won't move the
   cursor and the delta silently stalls.
4. Add `'dismissals' => $dismissals` to the final `response()->json([...])`.

### Wire shapes (duplicated inline — the contract this slice must honor)

```
POST /api/sync/prism/{gym}/interference-dismissals
  request:  { dismissals: [{ resolution_key: string }], removals: [{ resolution_key: string }] }
  response: { status: "ok", updated_at: "<iso8601 microsecond>" }

GET /api/sync/prism/{gym}/changes?since=<cursor>
  response adds:
    dismissals: [{ resolution_key: string, deleted_at: "<iso8601>"|null, updated_at: "<iso8601>" }]
```

## Files Changed

**New:**
- `database/migrations/<ts>_create_prism_interference_dismissals_table.php`
- `app/Sync/Models/PrismInterferenceDismissal.php`
- `app/Sync/Actions/UpsertPrismInterferenceDismissalsAction.php`
- `app/Sync/Controllers/PrismInterferenceDismissalController.php`
- `app/Sync/Requests/StorePrismInterferenceDismissalsRequest.php`
- `tests/Feature/Sync/Prism/InterferenceDismissalSyncTest.php` (write + tombstone + delta + cursor)
- `tests/Feature/Sync/Prism/InterferenceDismissalChangesTest.php` (the `/changes` sixth section + HWM)

**Modified:**
- `routes/sync.php` (one route in the `prism-write` group)
- `app/Sync/Controllers/PrismChangesController.php` (sixth section + cursor fold + response key)

## Implementation Phases

1. **Migration + model** — table mirroring `prism_exercise_overlay`; model mirroring `PrismExerciseOverlayRecord`.
2. **Action** — `UpsertPrismInterferenceDismissalsAction` copying `UpsertPrismExercisesAction` (upsert + tombstone + max-timestamp reduction).
3. **Checkpoint** — `php artisan test --parallel`.
4. **Request + controller + route** — FormRequest validation, `store` method, route in `prism-write` group.
5. **`/changes` sixth section** — query + map + cursor fold + response key.
6. **Tests** — write/tombstone round-trip; batched reset-all `removals[]`; delta `since` filtering; cursor HWM advances on dismissal change; cross-gym isolation (Cove rows never appear for Flagship).
7. **Final checkpoint** — `php artisan test --parallel`.

## Success Criteria

- [ ] `prism_interference_dismissals` migration exists with unique `[gym_id, resolution_key]`, index `[gym_id, updated_at]`, `deleted_at timestamp(6)` nullable, `timestamps(6)`.
- [ ] `POST /api/sync/prism/{gym}/interference-dismissals` upserts live rows and tombstones removals in one request; returns `{status, updated_at}`.
- [ ] A batched `removals[]` of N keys (reset-all) tombstones all N in a single call.
- [ ] `GET /api/sync/prism/{gym}/changes` returns a `dismissals` array; `since` filtering returns only at-or-after-cursor rows.
- [ ] A dismissal-only change advances the `/changes` cursor (HWM fold verified by test).
- [ ] The Cove's dismissals never appear in Flagship's `/changes` and vice versa.
- [ ] Logger performs no interference computation and never parses `resolution_key`.
- [ ] All tests pass (`php artisan test --parallel`).

## Constraints

- Forward-only migration; it runs against the real DB on Forge deploy to `main`. Ships on a branch.
- Server is a dumb store: single-row keyed upserts, indexed `updated_at >= cursor` range scans. No parsing of the key.
- Gym is the partition key; validate the gym exists (`ValidatesGym`), do not verify membership (Phase 1 trust-on-login).
- Reuse the existing `prism-write` / `prism-poll` throttles, `EnsureDeviceId`, `LogSyncRequest` — no new middleware.
- No `DB::` facade; Eloquent only. Constructor promotion; explicit return types.

## Risks

| Risk | Mitigation |
|------|------------|
| Cursor stalls because the dismissal `max()` wasn't folded into the HWM | Dedicated test: dismiss one key, assert the returned `/changes` cursor advanced past a pre-dismissal cursor. |
| `resolution_key` too short, long keys truncate silently | `string(512)`; a test with a long sorted-items key round-trips intact. |
| Tombstone ambiguity (reset modeled as a deleted row) | Reset = `deleted_at` write (second upsert), never a `DELETE`; delta emits the tombstoned row. Mirrors the overlay action exactly. |
| Microsecond timestamp precision lost on round-trip | Use `timestamps(6)` + the overlay model's date-format convention; assert sub-second instants survive. |
