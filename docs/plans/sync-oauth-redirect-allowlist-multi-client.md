# Sync OAuth Redirect Allowlist — Multi-Client Rename + Prism Origin

**Status:** proposed (not yet applied). **Repo:** `logger` only. **Driver:** Prism (prism.squirby.ai) is
joining the sync auth flow as a second first-class client of the `/api/sync` auth endpoints, alongside the
Athlete PWA. **Author context:** raised while wiring Prism's Google-OAuth login — code trace of
`AuthController::googleRedirect`/`googleCallback`.

> This is a code + ops note for Logger. It explains WHAT to change, WHY, and the exact blast radius. The
> **primary change (§1a) is required for Prism's Google OAuth to round-trip to Prism at all** — local OR
> prod — because today Logger controls the return target entirely from its own config and ignores the
> client. Do NOT assume "localhost already works"; it does not for Prism (see §1a).

---

## 0. TL;DR

1. **Required for Prism Google OAuth to work (code):** `googleRedirect` currently reads ONLY `device_id`
   from the request and sets the post-OAuth return origin from its OWN config (`services.athlete.url`). So
   the token is always bounced to Logger's configured app (Flagship/Athlete) — NEVER to Prism, local or
   prod. Logger must accept a **client-supplied return origin** (a query param on `googleRedirect`),
   allowlist-check it, and carry it through the OAuth `state`. Without this, Prism's Google login cannot
   complete. (§1a)
2. **Allowlist (required alongside #1):** the client-supplied origin is only honored if its host is on the
   redirect allowlist. Add Prism's hosts — `localhost` (dev) is already in the default; add
   `prism.squirby.ai` for prod — to `ATHLETE_ALLOWED_REDIRECT_HOSTS`. (§2)
3. **Naming (recommended):** the allowlist/config is no longer Athlete-specific — rename `ATHLETE_*` →
   client-neutral. Mechanical; blast radius in §4.
4. **Nothing to do on Google.** Prism is never a redirect target Google sees (§5).

---

## 1. How the flow actually works (and where it's hardcoded to one client)

The sync Google-OAuth flow is a server-side redirect chain owned by Logger. A client app (Athlete or Prism)
never talks to Google directly:

1. Client browser → `GET {logger}/api/sync/auth/google/redirect?device_id=...`. **Today Logger reads ONLY
   `device_id` here** and sets the return origin itself:
   `$athleteUrl = config('services.athlete.url', 'https://squirby.app')` — a single configured app, NOT the
   requesting client.
2. Logger (`googleRedirect`) base64-encodes `state = { device_id, athlete_url }` (that `athlete_url` is the
   config value, not the client's), then redirects to Google with Logger's OWN callback as the registered
   redirect URI (`{logger}/api/sync/auth/google/callback`).
3. Google → Logger's callback.
4. Logger (`googleCallback`) decodes `state`, runs `resolveSafeRedirectUrl($state['athlete_url'])` (which,
   because the value came from Logger's own config, is trivially "safe"), exchanges the code, mints a Sanctum
   token, auto-creates the user if new (`findOrCreateSocialUser`), and redirects the **browser** to
   `{athleteUrl}/auth/callback?token=&athlete=&email=` (or `?error=`).

**The problem for Prism:** `athlete_url` is Logger's config default, so step 4 always returns to
Flagship/Athlete — never Prism. The `resolveSafeRedirectUrl` allowlist exists to safely honor a
CLIENT-supplied origin, but no client origin is read in step 1. So the allowlist is currently moot for
routing (it only ever sees Logger's own config host).

### 1a. Required change — read + allowlist-check a client-supplied return origin

Make `googleRedirect` accept the requesting client's origin and carry it through `state`:
- Read a return origin from the request, e.g. `$request->query('redirect_origin')` (or reuse an
  `athlete_url` query param name — pick one; `redirect_origin` is clearer and client-neutral).
- Validate it immediately with the EXISTING `resolveSafeRedirectUrl()` against the allowlist; use the result
  (which falls back to the config default if the host isn't allowlisted) as `athlete_url` in `state`.
- `googleCallback` already calls `resolveSafeRedirectUrl` on the decoded `state` — keep that as the
  defense-in-depth second check.

This is the security-sensitive hop: the client origin is **unsigned and attacker-controllable**, so it MUST
be allowlist-checked (that's what `resolveSafeRedirectUrl` is for) — never redirect a freshly-minted token to
an arbitrary host. With this change, a client passes `?device_id=...&redirect_origin=<its origin>` and, if
its host is allowlisted, the token returns to it.

Prism's side (already built) sends only `device_id` today and will add `redirect_origin=window.location.origin`
once this Logger change exists — tracked so the two land together.

**The allowlist (§2) is only meaningful once §1a exists** — it guards the client-supplied origin §1a
introduces. Both are needed; §1a alone (no allowlist entry) would reject Prism's origin and fall back to the
default; the allowlist alone (no §1a) is never consulted for a client origin.

---

## 2. Allowlist entries (required alongside §1a)

Current allowlist default (`config/services.php`): `squirby.app, flagship.squirby.ai, localhost`.

- **Local dev:** `localhost` is already present — so once §1a reads the client origin, local Prism
  (`http://localhost:<vite-port>`) is honored with NO further allowlist change. (Earlier framing that
  "localhost already works" was about the allowlist only; it does NOT work end-to-end until §1a lands.)
- **Production:** `prism.squirby.ai` is NOT present and must be added, or a prod Prism Google login (even
  with §1a) would have its origin rejected and fall back to `flagship.squirby.ai` — token lands on the wrong
  app.

**Fix (production Logger env — Forge env on the prod box):**
```
ATHLETE_ALLOWED_REDIRECT_HOSTS=squirby.app,flagship.squirby.ai,prism.squirby.ai,localhost
```
Then clear/rebuild config cache (`php artisan config:cache`). (If the rename in §3/§4 is done first, use the
new var name instead — see §4.)

No code change is required for the production fix alone — the var is already read via `env()`.

---

## 3. Local development

Needs §1a (the client-origin read), NOT an allowlist change: `localhost` is already allowlisted, so once
Logger reads + allowlist-checks the client origin, local Prism (`http://localhost:<vite-port>`) round-trips
with no further config. Prism's local `.env` already points `VITE_LOGGER_API_URL` at
`http://localhost:8000/api/sync`. Until §1a lands, local Prism Google login does NOT return to Prism (the
token goes to Logger's configured default app).

---

## 4. Recommended rename — `ATHLETE_*` → client-neutral

**Why.** The `/api/sync` auth endpoints are a SHARED backend for multiple client apps. `ATHLETE_APP_URL`
and `ATHLETE_ALLOWED_REDIRECT_HOSTS` (and the `services.athlete.*` config key) read as Athlete-specific, but
they now govern Prism too (and any future client). The name misleads a future maintainer into thinking it's
Athlete-only. A client-neutral name (e.g. `SYNC_CLIENT_*` / `services.sync_clients.*`, or
`SYNC_ALLOWED_REDIRECT_HOSTS` + `SYNC_DEFAULT_CLIENT_URL`) describes reality.

> Naming is a judgment call for the Logger owner — pick the convention that fits Logger. The point is
> *client-neutral*, not the specific token. Suggestions below use `SYNC_*` for concreteness.

**Blast radius (every current reference — verified):**

| File | Line(s) | Current | Notes |
|------|---------|---------|-------|
| `config/services.php` | ~60–72 | `'athlete' => [ 'url' => env('ATHLETE_APP_URL', …), 'allowed_redirect_hosts' => env('ATHLETE_ALLOWED_REDIRECT_HOSTS', …) ]` | The config definition. Rename the key to e.g. `'sync_clients'` and both env vars. |
| `app/Sync/Controllers/AuthController.php` | 72, 269–287 | `config('services.athlete.url')`, `config('services.athlete.allowed_redirect_hosts')`, the `resolveSafeRedirectUrl()` doc comment, and `$athleteUrl` / `athlete_url` local/state names | Update the config reads; optionally rename the `$athleteUrl` var + the `athlete_url` state key for clarity (the state key is a wire contract with CLIENTS — see the caveat below). |
| `app/Providers/AppServiceProvider.php` | ~42 | `config('services.athlete.url')` in the password-reset URL builder | Also points reset links at a single client — worth revisiting separately (it hardcodes ONE client; multi-client reset is a bigger question, out of scope here). Update the config read at minimum. |
| `.env` (+ prod/staging on the boxes) | — | `ATHLETE_APP_URL`, `ATHLETE_ALLOWED_REDIRECT_HOSTS` | Rename the keys; keep values. |

**⚠️ Wire-contract caveat (do NOT rename blindly).** The OAuth `state` field is named `athlete_url` and is
SENT BY THE CLIENTS (Athlete today, Prism in P1). It is a cross-app contract: if you rename the `state` key
in Logger, every client that sets it must change in lockstep, or OAuth breaks for the un-updated client.
**Recommendation:** rename the ENV VARS + the `services.*` CONFIG KEY + internal PHP variables freely (purely
internal to Logger), but treat the `athlete_url` **state field name** as a frozen wire contract for now —
leave it, or coordinate a synchronized rename across Logger + Athlete + Prism as a separate, deliberate
change. (Per the repos' cross-app rule: a shared wire shape is reconciled across apps, not changed
unilaterally.)

**Backward-compat during migration (optional, safer):** read the new var with the old as a fallback, e.g.
`env('SYNC_ALLOWED_REDIRECT_HOSTS', env('ATHLETE_ALLOWED_REDIRECT_HOSTS', '<default>'))`, so the box env can
be migrated without a flag-day. Drop the fallback once all env files are updated.

---

## 5. Google side — nothing to do

Confirmed: Google only ever redirects to **Logger's** callback
(`{logger}/api/sync/auth/google/callback`), which Athlete already uses with the same Google OAuth client. So
the Authorized Redirect URIs registered with Google (`https://squirby.ai/api/sync/auth/google/callback` +
the local variant) already exist and are unchanged by Prism. Prism's origin is NOT a Google redirect URI and
does NOT need to be added to Google Cloud Console.

Only caveat (launch-time, not now): if the Google OAuth **consent screen** is still in "Testing" mode, only
allowlisted test-user emails can sign in. Add any additional Cove-staff emails as test users (or publish the
consent screen) before launch. Existing test users (and the dev account) are unaffected.

---

## 6. Why this doc lives in Logger (not Prism)

Per the cross-app rules, each repo owns its own detail and history; an app never reaches into another's
source. The redirect allowlist, the `services.*` config, and the OAuth controller are all Logger's. Prism's
side (sending its origin in the OAuth initiation, landing on `/auth/callback`) is handled in Prism's own M3
work. This note is the Logger-side half of that coordination.
