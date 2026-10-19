# Sync OAuth — From Single-Client to Multi-Client Auth (Prism Onboarding)

**Status:** proposed (not yet applied). **Repo:** `logger` only. **Driver:** Prism (`prism.squirby.ai`) is
joining the sync auth flow as a second first-class client of the `/api/sync` auth endpoints, alongside the
live Athlete PWA. **Hard constraint (user-stated, non-negotiable):** **Athlete is live in production with
real users and must continue to work with ZERO changes — no Athlete-repo edits, no Athlete redeploy, no
change to Athlete's behavior.** The safe scope of this change is §2a + §2b only (Logger-side, additive);
the rename (§3) and the latent debt (§5) are deferred to separate later changes. See §0a for the full
safety guarantee and §7/§7a for sequencing + the regression-lock tests.

> This is a code + ops note for Logger. It explains the real modeling error (not just "add a host to an
> allowlist"), WHAT to change, WHY, the exact blast radius, and the safety rules that protect the live
> Athlete flow. Verified against the current source (`app/Sync/Controllers/AuthController.php`,
> `config/services.php`, `app/Providers/AppServiceProvider.php`) on 2026-10-19.

---

## 0. TL;DR

0. **Non-negotiable safety guarantee: Athlete is NOT changed, at all.** No file in the Athlete repo is
   edited, and Athlete is not redeployed. Athlete keeps sending exactly the request it sends today (only
   `device_id`, no return origin), and Logger keeps responding to that request byte-for-byte as it does
   today. The new behavior is OPT-IN and only Prism opts into it. This is the acceptance bar for the whole
   change: if any step would alter Athlete's behavior, that step is wrong. (§0a)
1. **The real problem is a single-client assumption baked into the design, not a missing allowlist entry.**
   The auth backend was modeled as if it serves exactly one app (Athlete). The return-origin for a minted
   token is read from Logger's OWN config, and the initiating client is ignored. Prism doesn't add a
   feature — it falsifies that latent assumption. (§1)
2. **Required code fix (§2a):** `googleRedirect` must accept a client-supplied return origin
   (`?redirect_origin=...`), allowlist-check it with the EXISTING `resolveSafeRedirectUrl()`, and carry the
   validated result through the OAuth `state`. This is the single→multi flip. It is **backward-compatible**:
   when the param is absent (as Athlete sends today), the server falls back to the config default — the live
   flow is a strict subset of the new flow.
3. **Allowlist = client registry (§2b):** once origins are honored, the allowlist stops being defensive
   trivia and becomes the list of apps allowed to use this backend. Add `prism.squirby.ai` for prod;
   `localhost` already covers both apps in dev. Env-only change, no code.
4. **Naming (§3):** `ATHLETE_*` / `services.athlete.*` now assert a falsehood ("one client, it's Athlete").
   Rename to client-neutral — but ONLY the Logger-internal names. Frozen wire contracts stay (§4).
5. **Latent debt the OAuth fix does NOT reach (§5):** the same single-client assumption is duplicated in the
   password-reset link and the `'athlete'` response key. Flagged, scoped out, not forgotten.
6. **Nothing to do on Google (§6).** Prism is never a redirect target Google sees.

---

## 0a. The safety guarantee — Athlete is untouched (read this before anything else)

The user's hard requirement: **Athlete must continue to work with zero changes.** This section states
exactly why the plan satisfies that and what keeps it true. Every later section is subordinate to this one.

**What makes it safe (the mechanism, not a promise).** The change adds an OPTIONAL return-origin input to
`googleRedirect`. Athlete does not send it and will not start sending it. When the input is absent,
`resolveSafeRedirectUrl(null)` returns the configured default — which is Athlete — so Logger's response to
Athlete's request is byte-for-byte identical to today. The live Athlete flow is a strict SUBSET of the new
flow; the new code path is only reachable when a client (Prism) actively supplies an origin.

**Blast radius is Logger-only.**
- **Zero Athlete-repo edits.** No file in the Athlete codebase is opened, modified, committed, or
  redeployed as part of this work. If a step calls for touching Athlete, the step is wrong.
- The entire change is in the **Logger** repo: one behavior change in `AuthController::googleRedirect`, one
  production env line (`allowed_redirect_hosts` gains `prism.squirby.ai`), and tests.

**Hard rules that keep Athlete safe (binding on implementation):**
1. **Additive only.** `googleRedirect` gains the ability to READ a new optional `redirect_origin`. It does
   NOT change what happens when that param is absent. The absent-param branch must remain the existing
   behavior, unchanged.
2. **No frozen wire names change in this PR.** The `athlete_url` state field, the `'athlete'` response key,
   and the route paths stay exactly as they are (§4). Athlete parses these; touching them is a flag-day.
3. **The rename (§3) is OUT of this change.** The `ATHLETE_*` → `SYNC_*` rename touches live Forge env vars —
   the one realistic way to break the live box — and is cosmetic. It is deferred to its own separate change
   (§7). This safety-first PR does not rename anything.
4. **An automated test locks Athlete's path.** A test asserting "no `redirect_origin` → redirect goes to the
   Athlete default" ships WITH the change and must pass. That test is the regression lock on this guarantee
   (§7a).

**How we prove it before shipping:** the test suite in §7a (absent → default, allowlisted → honored,
non-allowlisted → falls back) plus a re-run of the full existing sync-auth suite (which already asserts
Athlete's `athlete_url` state behavior, `GoogleOAuthRedirectTest.php`). Green on both = Athlete unchanged.

---

## 1. The real problem: a single-client assumption, not a config gap

The flow has two independent notions of "who is the client," and they are wired backwards:

1. **Who initiates** — the app that hits `GET /api/sync/auth/google/redirect`.
2. **Who receives the token** — the origin the freshly-minted token is bounced back to.

In a correct multi-client model, #2 is **derived from #1**: whoever starts the flow gets the token back. In
the current code, #2 is **hardcoded from Logger's own config** (`services.athlete.url`) and #1 is **ignored**
(only `device_id` is read). The server has therefore decided, before the flow begins, that there is exactly
one app and it is Athlete. That is the single-client assumption — **structural, not a config value.**

`resolveSafeRedirectUrl()` LOOKS like multi-client support, but it is defensive plumbing with nothing to
defend: it validates a client-supplied origin that no client is permitted to supply. §2a is what actually
flips the model from single to multi; the allowlist only becomes meaningful BECAUSE §2a introduces an
untrusted input worth checking.

**Conclusion:** §2a is not an enhancement — it is the fix for the modeling error. Everything else is
consequence.

---

## 2. How the flow works today, and the required change

A client app never talks to Google directly; the flow is a server-side redirect chain owned by Logger.

1. Client browser → `GET {logger}/api/sync/auth/google/redirect?device_id=...`. **Today Logger reads ONLY
   `device_id`** (`AuthController::googleRedirect`, ~line 71) and sets the return origin from its own config:
   `$athleteUrl = config('services.athlete.url', 'https://squirby.app')` — a single configured app, NOT the
   requesting client.
2. Logger base64-encodes `state = { device_id, athlete_url }` (that `athlete_url` is the CONFIG value, not
   the client's), then redirects to Google with Logger's OWN callback as the registered redirect URI
   (`{logger}/api/sync/auth/google/callback`).
3. Google → Logger's callback.
4. Logger (`googleCallback`) decodes `state`, runs `resolveSafeRedirectUrl($state['athlete_url'])` (trivially
   "safe" because the value came from Logger's own config), exchanges the code, mints a Sanctum token,
   auto-creates the user if new (`findOrCreateSocialUser`), and redirects the BROWSER to
   `{athleteUrl}/auth/callback?token=&athlete=&email=` (or `?error=`).

**Why this fails Prism:** `athlete_url` is Logger's config default, so step 4 ALWAYS returns to
Flagship/Athlete — never Prism, local or prod.

### 2a. Required change — read + allowlist-check a client-supplied return origin

Make `googleRedirect` accept the requesting client's origin and carry it through `state`:

- Read an origin from the request: `$request->query('redirect_origin')` (clearer + client-neutral; prefer
  this over reusing `athlete_url` as a query-param name).
- Validate it IMMEDIATELY with the EXISTING `resolveSafeRedirectUrl()` against the allowlist. The result
  (which falls back to the config default when the host isn't allowlisted, or when the param is ABSENT) is
  what goes into `state` as `athlete_url`.
- `googleCallback` already calls `resolveSafeRedirectUrl` on the decoded state — keep that as
  defense-in-depth.

This is the security-sensitive hop: the client origin is **unsigned and attacker-controllable**, so it MUST
be allowlist-checked — never redirect a freshly-minted token to an arbitrary host. `resolveSafeRedirectUrl`
already does exactly this; it has simply never been fed a real candidate.

**Backward-compat (the live-Athlete safety guarantee — see §0a):** Athlete keeps sending only `device_id`.
With no `redirect_origin`, `resolveSafeRedirectUrl(null)` returns the config default — byte-for-byte today's
behavior. **Implementation rule: do not alter the absent-param branch.** The new code only READS the param
when present; when it is absent, `googleRedirect` must behave exactly as it does today (default origin into
`state`). The live flow is a strict subset of the new flow; Athlete cannot be disrupted by this change.
Prism's side (already built) sends only `device_id` today and adds `redirect_origin=window.location.origin`
once this Logger change exists — the two land together. Nothing in the Athlete repo is edited.

### 2b. Allowlist entries — the client registry (required alongside §2a)

Current default (`config/services.php`, ~line 71): `squirby.app, flagship.squirby.ai, localhost`.

- **Local dev:** `localhost` is already present — once §2a reads the client origin, local Prism
  (`http://localhost:<vite-port>`) is honored with NO further allowlist change.
- **Production:** `prism.squirby.ai` is NOT present and must be added, or a prod Prism login (even with §2a)
  has its origin rejected and falls back to `flagship.squirby.ai` — token lands on the wrong app.

```
# production Logger env (Forge), then: php artisan config:cache
ATHLETE_ALLOWED_REDIRECT_HOSTS=squirby.app,flagship.squirby.ai,prism.squirby.ai,localhost
# (use the renamed var instead if §3 lands first)
```

No code change for the prod fix alone — the var is already read via `env()`. Conceptually this host list has
been **promoted from a safety net to a first-class concept**: the registered clients of the sync backend.

---

## 3. Recommended rename — `ATHLETE_*` → client-neutral  *(DEFERRED — NOT part of the safe change; see §7)*

> **Scope note:** This rename is cosmetic and touches live Forge env vars — the one realistic way to break
> the live box. It is explicitly OUT of the safety-first change (§0a rule 3, §7 step 2) and is written here
> only so the follow-up is planned. Do not do this as part of the Prism-unblocking PR.

**Why.** `ATHLETE_APP_URL`, `ATHLETE_ALLOWED_REDIRECT_HOSTS`, `services.athlete.*`, and `$athleteUrl` all
assert "there is one client and it's Athlete." That assertion is now FALSE. A client-neutral name stops a
future maintainer from re-baking the single-client assumption back in.

### Naming options

**Option A — `SYNC_*` (recommended).** Names the thing by the backend it belongs to.
```
SYNC_DEFAULT_CLIENT_URL=https://flagship.squirby.ai
SYNC_ALLOWED_REDIRECT_HOSTS=squirby.app,flagship.squirby.ai,prism.squirby.ai,localhost
config('services.sync.default_client_url')
config('services.sync.allowed_redirect_hosts')
```
- Pros: matches the vocabulary already in the codebase (`app/Sync/`, `sync-per-user`/`sync-global` rate
  limiters, `StoreSyncLogAction`); least surprise; client-neutral.
- Cons: "default client url" is a slight mouthful.

**Option B — `OAUTH_*` / redirect-centric.**
```
OAUTH_DEFAULT_REDIRECT_URL=https://flagship.squirby.ai
OAUTH_ALLOWED_REDIRECT_HOSTS=...
config('services.oauth.default_redirect_url')
```
- Pros: precise about the mechanism (redirect-target allowlisting).
- Cons: the SAME config is also read by the password-reset link (§5), which is NOT OAuth — `OAUTH_*` would
  mislead there. Narrower than reality.

**Option C — `SYNC_CLIENT_*` (registry framing).**
```
SYNC_CLIENT_DEFAULT_URL=https://flagship.squirby.ai
SYNC_CLIENT_ALLOWED_HOSTS=...
config('services.sync_clients.default_url')
```
- Pros: the name teaches the model — "registered client apps of the sync backend"; best at preventing the
  single-client assumption from returning.
- Cons: implies a richer registry than exists (it's still a flat host list + a default); mild over-promise.

**Recommendation: Option A.** Get Option C's teaching benefit via a config comment rather than a grander name.

### Blast radius (every current reference — verified)

| File | Line(s) | Current | Action |
|------|---------|---------|--------|
| `config/services.php` | ~61, ~71 | `env('ATHLETE_APP_URL', …)`, `env('ATHLETE_ALLOWED_REDIRECT_HOSTS', …)` under the `'athlete'` key | Rename the key + both env vars. Read **new-with-old-fallback** (below). |
| `app/Sync/Controllers/AuthController.php` | 72, 96, 275 (reads); 269 (doc) | `config('services.athlete.url')`, `config('services.athlete.allowed_redirect_hosts')`, `$athleteUrl` local | Update config reads; rename the `$athleteUrl` local freely. Do NOT rename the `athlete_url` STATE key (§4). |
| `app/Providers/AppServiceProvider.php` | ~42 | `config('services.athlete.url')` in the reset-link builder | Update the config read. The single-client behavior here is separate debt (§5). |
| `.env` + Forge prod/staging boxes | — | `ATHLETE_APP_URL`, `ATHLETE_ALLOWED_REDIRECT_HOSTS` | Rename keys, keep values. |

**Safety for the env rename (the only way it can break the live box):** env vars live on the Forge prod box.
Read new-with-old-fallback so a box still holding the old key can't null out:
```php
'url' => env('SYNC_DEFAULT_CLIENT_URL', env('ATHLETE_APP_URL', 'https://flagship.squirby.ai')),
'allowed_redirect_hosts' => env('SYNC_ALLOWED_REDIRECT_HOSTS', env('ATHLETE_ALLOWED_REDIRECT_HOSTS', '<default>')),
```
Drop the fallback once every box env is migrated.

### Fix the inconsistent inline default while you're in here

The config default URL is currently **inconsistent across call sites** (only bites when the env var is unset,
but the four sites disagree):

| Location | Inline fallback |
|----------|-----------------|
| `AuthController::googleRedirect` (72) | `https://squirby.app` |
| `AuthController::resolveSafeRedirectUrl` (275) | `https://flagship.squirby.ai` |
| `config/services.php` (61) | `https://flagship.squirby.ai` |
| `AppServiceProvider` (42) | `https://squirby.app` |

Consolidate to one value during the rename. Since Athlete is live, the canonical default is whatever prod's
`ATHLETE_APP_URL` actually is — treat `https://flagship.squirby.ai` as canonical unless the prod value differs.

---

## 4. FROZEN wire contracts — do NOT rename (Athlete is live on them)

The temptation, once you've decided "this was modeled for one app," is to rename EVERYTHING Athlete-flavored
in one sweep. Do not. These cross the wire to live Athlete clients/servers; renaming any of them is a
flag-day that breaks production users:

- **`athlete_url` STATE field** — sent by the clients, read by Logger, and asserted by
  `tests/Feature/Sync/GoogleOAuthRedirectTest.php` (lines 108, 149, 216, 258). Frozen, or coordinate a
  synchronized rename across Logger + Athlete + Prism as a separate, deliberate change.
- **`'athlete' => $user->name` response key** — in `authResponse()` and the `googleCallback` redirect params
  (`?athlete=...`). Athlete's callback page parses these. Frozen.
- **Route paths** `/api/sync/auth/google/redirect` + `/callback` — registered with Google. Frozen.

Rename the internal/env/config names freely; change wire names only in lockstep across all apps. That is the
difference between fixing the model and breaking both clients. (Per the repos' cross-app rule: a shared wire
shape is reconciled across apps, not changed unilaterally.)

---

## 5. Latent single-client debt the OAuth fix does NOT reach

Your diagnosis ("modeled for one app") is proven by the assumption being DUPLICATED outside the OAuth
controller. The §2a/§2b fix does not touch these; each needs its own multi-client answer later:

- **Password-reset link (`AppServiceProvider` line 42).** The reset URL is hardcoded to
  `services.athlete.url`, so a Prism user's reset email points at the ATHLETE app. Reset has NO `state` to
  carry an origin, so multi-client reset is genuinely harder — the server must know which app a user
  "belongs to," or the email must encode it. **Out of scope here; flagged.**
- **The `'athlete'` response key.** A baked-in "the client is Athlete" wire contract (also frozen, §4).
  Functionally harmless to Prism, but the same smell. **Out of scope; noted.**

---

## 6. Google side — nothing to do

Google only ever redirects to **Logger's** callback (`{logger}/api/sync/auth/google/callback`), which Athlete
already uses with the same Google OAuth client. The Authorized Redirect URIs registered with Google already
exist and are unchanged by Prism. **Prism's origin is NOT a Google redirect URI** and does not need adding to
Google Cloud Console.

Launch-time caveat only: if the OAuth **consent screen** is still in "Testing" mode, only allowlisted
test-user emails can sign in — add any additional staff emails as test users (or publish the screen) before
launch. Existing test users and the dev account are unaffected.

---

## 7. Sequencing (keeps the risky-sounding diff away from the behavior fix)

Ordered so the live-Athlete constraint (§0a) is trivial to reason about. **Only step 1 is part of this
safety-first change.** Steps 2 and 3 are explicitly deferred to their own later, separate changes — they are
NOT bundled here, because bundling them is what would put Athlete at risk.

1. **§2a + §2b — the behavior fix Prism actually needs (THIS change).** Small, additive, backward-compatible,
   Logger-only. Ships with the §7a test suite and a green re-run of the existing sync-auth suite. Athlete
   untouched. **This is the whole scope of the safe PR.**
2. **§3 — the cosmetic `ATHLETE_*` → `SYNC_*` rename — DEFERRED, separate change (not now).** It touches live
   Forge env vars (the one realistic way to break the live box) and buys no functionality. When it is done
   later, it uses new-with-old env fallback + the inline-default consolidation, and re-runs the full sync
   auth suite. Keeping it out of the launch-unblocking change is a deliberate safety decision.
3. **§5 — multi-client reset + response-key — DEFERRED, separate changes (not now).** Harmless to Prism's
   pilot (Google-only, no password reset). Each gets its own deliberate change if/when needed.

### 7a. The required tests (ship WITH step 1; the regression lock on §0a)

Add to the Google-OAuth-redirect feature test (`tests/Feature/Sync/GoogleOAuthRedirectTest.php`):

1. **Athlete path unchanged (the invariant lock).** A redirect request with NO `redirect_origin` (exactly
   what Athlete sends — only `device_id`) produces a `state` carrying the Athlete default origin, and the
   eventual callback redirects to the Athlete default. This test failing = the safety guarantee is broken;
   it must be green before merge.
2. **Prism path honored.** A request with `redirect_origin` set to an allowlisted host (e.g. local Prism, or
   `prism.squirby.ai`) carries that origin through and the callback redirects there.
3. **Untrusted origin rejected.** A request with a `redirect_origin` whose host is NOT allowlisted falls back
   to the Athlete default (never redirects a minted token to an arbitrary host).

Then re-run the FULL existing sync-auth suite (it already asserts Athlete's `athlete_url` state behavior at
`GoogleOAuthRedirectTest.php` lines 108/149/216/258). Green on the new cases + green on the existing suite =
proof Athlete is unchanged.

---

## 8. Why this doc lives in Logger (not Prism)

Per the cross-app rules, each repo owns its own detail; an app never reaches into another's source. The
redirect allowlist, the `services.*` config, and the OAuth controller are all Logger's. Prism's side (sending
its origin in the OAuth initiation, landing on `/auth/callback`) is handled in Prism's own work. This note is
the Logger-side half of that coordination.
