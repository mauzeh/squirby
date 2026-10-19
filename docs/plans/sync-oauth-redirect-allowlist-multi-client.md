# Sync OAuth — From Single-Client to Multi-Client Auth (Prism Onboarding)

**Status:** proposed (not yet applied). **Repo:** `logger` only. **Driver:** Prism (`prism.squirby.ai`) is
joining the sync auth flow as a second first-class client of the `/api/sync` auth endpoints, alongside the
live Athlete PWA. **Hard constraint:** **Athlete is live in production with real users — its auth flow must
not be disrupted.** Every change below is additive/backward-compatible for Athlete.

> This is a code + ops note for Logger. It explains the real modeling error (not just "add a host to an
> allowlist"), WHAT to change, WHY, the exact blast radius, and the safety rules that protect the live
> Athlete flow. Verified against the current source (`app/Sync/Controllers/AuthController.php`,
> `config/services.php`, `app/Providers/AppServiceProvider.php`) on 2026-10-19.

---

## 0. TL;DR

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

**Backward-compat (the live-Athlete safety guarantee):** Athlete keeps sending only `device_id`. With no
`redirect_origin`, `resolveSafeRedirectUrl(null)` returns the config default — byte-for-byte today's
behavior. The live flow is a strict subset of the new flow; Athlete cannot be disrupted by this change.
Prism's side (already built) sends only `device_id` today and adds `redirect_origin=window.location.origin`
once this Logger change exists — the two land together.

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

## 3. Recommended rename — `ATHLETE_*` → client-neutral

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

Recommended order, so the live-Athlete constraint is easy to reason about:

1. **§2a + §2b — behavior fix Prism actually needs.** Small, additive, backward-compatible. Add a test that
   (a) absent `redirect_origin` → falls back to default (Athlete's path, unchanged) and (b) an allowlisted
   `redirect_origin` is honored and (c) a non-allowlisted one falls back.
2. **§3 — purely-cosmetic rename**, as a SEPARATE change, with new-with-old env fallback and the inline-default
   consolidation. Run the full sync auth test suite after.
3. **§5 — multi-client reset + response-key** questions, deferred, each its own deliberate change.

---

## 8. Why this doc lives in Logger (not Prism)

Per the cross-app rules, each repo owns its own detail; an app never reaches into another's source. The
redirect allowlist, the `services.*` config, and the OAuth controller are all Logger's. Prism's side (sending
its origin in the OAuth initiation, landing on `/auth/callback`) is handled in Prism's own work. This note is
the Logger-side half of that coordination.
