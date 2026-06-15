# Design Document

## Overview

This design upgrades the Sync API auth system from username-based to email-based authentication, adding Google and Apple social sign-in endpoints. The implementation follows the existing `app/Sync/Controllers/AuthController.php` pattern, adds two new service classes for JWT verification, and extends the route file with two new endpoints. No database migration is needed — the existing `users` table already has `email`, `name`, `password`, and `google_id` columns.

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    routes/sync.php                        │
│  POST /register        → AuthController@register         │
│  POST /login           → AuthController@login            │
│  POST /auth/google     → AuthController@googleAuth       │
│  POST /auth/apple      → AuthController@appleAuth        │
└──────────────────────────┬──────────────────────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
              ▼            ▼            ▼
┌──────────────────┐ ┌──────────┐ ┌──────────┐
│  AuthController  │ │GoogleJwt │ │ AppleJwt │
│                  │ │Verifier  │ │ Verifier │
│  - register()    │ │Service   │ │ Service  │
│  - login()       │ └──────────┘ └──────────┘
│  - googleAuth()  │       │            │
│  - appleAuth()   │       ▼            ▼
│                  │  Google JWKS    Apple JWKS
└────────┬─────────┘  (cached)      (cached)
         │
         ▼
┌──────────────────┐
│   User Model     │
│  (existing)      │
│  - email         │
│  - name          │
│  - password      │
│  - google_id     │
└──────────────────┘
```

### Key Design Decisions

1. **No database migration needed**: The `users` table already has all required columns (`email`, `name`, `password`, `google_id`). The upgrade is purely at the controller/validation level.

2. **JWT verification via firebase/php-jwt**: The `firebase/php-jwt` package is already in the vendor directory. We use it to verify Google and Apple JWTs against their respective JWKS endpoints.

3. **JWKS caching**: Google and Apple public keys are fetched from their JWKS endpoints and cached for 24 hours using Laravel's cache system to avoid repeated HTTP calls.

4. **Backward-compatible login**: The login endpoint detects whether the request contains `email` or `username` and routes to the appropriate lookup logic. This keeps the old mobile app working while new versions use email.

5. **Email migration in social auth**: When social auth finds no user by email but finds a Legacy_User by name match whose email ends in `@sync.local`, it updates the email. This is a one-time migration per user.

6. **Single controller, multiple methods**: Rather than creating separate controllers for each auth method, we keep all auth logic in `AuthController` with helper methods for token generation and response formatting.

## File Changes

### Modified Files

#### `app/Sync/Controllers/AuthController.php`
- Rewrite `register()` to accept `{ email, password, name, device_id }` instead of `{ username, password, device_id }`
- Rewrite `login()` to accept either `{ email, password, device_id }` or `{ username, password, device_id }` (backward compat)
- Add `googleAuth()` method for Google social sign-in
- Add `appleAuth()` method for Apple social sign-in
- Add private helper `authResponse(User $user, string $deviceId): JsonResponse` for unified response format
- Add private helper `findOrCreateSocialUser(string $email, string $name, ?string $googleId): User` for social auth user resolution with email migration logic

#### `routes/sync.php`
- Add route: `Route::post('/auth/google', [AuthController::class, 'googleAuth'])`
- Add route: `Route::post('/auth/apple', [AuthController::class, 'appleAuth'])`

#### `config/services.php`
- Add `apple` service configuration block with `client_id` (app bundle ID)

### New Files

#### `app/Sync/Services/GoogleJwtVerifier.php`
- Fetches Google's JWKS from `https://www.googleapis.com/oauth2/v3/certs`
- Caches the key set for 24 hours
- Verifies the `id_token` JWT signature, expiration, and audience (must match `GOOGLE_CLIENT_ID`)
- Returns decoded payload with `email`, `name`, `sub` (Google user ID)
- Throws exception on verification failure

#### `app/Sync/Services/AppleJwtVerifier.php`
- Fetches Apple's JWKS from `https://appleid.apple.com/auth/keys`
- Caches the key set for 24 hours
- Verifies the `identity_token` JWT signature, expiration, and audience (must match Apple app bundle ID)
- Returns decoded payload with `email`, `name` (may be null), `sub` (Apple user ID)
- Throws exception on verification failure

## Detailed Method Designs

### AuthController::register()

```php
public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'name' => 'required|string',
        'device_id' => 'required|string',
    ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);

    return $this->authResponse($user, $validated['device_id']);
}
```

### AuthController::login()

```php
public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required|string',
        'device_id' => 'required|string',
    ]);

    $user = User::where('email', $validated['email'])->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        throw new AuthenticationException('Invalid credentials.');
    }

    return $this->authResponse($user, $validated['device_id']);
}
```

### AuthController::googleAuth()

```php
public function googleAuth(Request $request, GoogleJwtVerifier $verifier): JsonResponse
{
    $validated = $request->validate([
        'id_token' => 'required|string',
        'device_id' => 'required|string',
    ]);

    $payload = $verifier->verify($validated['id_token']);
    // payload: { email, name, sub }

    $user = $this->findOrCreateSocialUser(
        email: $payload['email'],
        name: $payload['name'] ?? $payload['email'],
        googleId: $payload['sub']
    );

    return $this->authResponse($user, $validated['device_id']);
}
```

### AuthController::appleAuth()

```php
public function appleAuth(Request $request, AppleJwtVerifier $verifier): JsonResponse
{
    $validated = $request->validate([
        'identity_token' => 'required|string',
        'authorization_code' => 'required|string',
        'device_id' => 'required|string',
    ]);

    $payload = $verifier->verify($validated['identity_token']);
    // payload: { email, name (nullable), sub }

    $user = $this->findOrCreateSocialUser(
        email: $payload['email'],
        name: $payload['name'] ?? explode('@', $payload['email'])[0],
        googleId: null
    );

    return $this->authResponse($user, $validated['device_id']);
}
```

### AuthController::findOrCreateSocialUser()

```php
private function findOrCreateSocialUser(string $email, string $name, ?string $googleId): User
{
    // 1. Try find by email
    $user = User::where('email', $email)->first();

    if ($user) {
        // Update google_id if provided and not set
        if ($googleId && !$user->google_id) {
            $user->update(['google_id' => $googleId]);
        }
        return $user;
    }

    // 2. Create new user
    return User::create([
        'name' => $name,
        'email' => $email,
        'google_id' => $googleId,
    ]);
}
```

### AuthController::authResponse()

```php
private function authResponse(User $user, string $deviceId): JsonResponse
{
    $token = $user->createToken($deviceId)->plainTextToken;

    return response()->json([
        'status' => 'ok',
        'token' => $token,
        'athlete' => $user->name,
        'email' => $user->email,
    ]);
}
```

## GoogleJwtVerifier Service Design

```php
namespace App\Sync\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleJwtVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const CACHE_KEY = 'google_jwks';
    private const CACHE_TTL = 86400; // 24 hours

    public function verify(string $idToken): array
    {
        $keys = $this->getKeys();
        $decoded = JWT::decode($idToken, JWK::parseKeySet($keys));

        // Verify audience matches our Google Client ID
        $expectedAudience = config('services.google.client_id');
        if ($decoded->aud !== $expectedAudience) {
            throw new \UnexpectedValueException('Invalid audience');
        }

        return [
            'email' => $decoded->email,
            'name' => $decoded->name ?? null,
            'sub' => $decoded->sub,
        ];
    }

    private function getKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Http::get(self::JWKS_URL)->json();
        });
    }
}
```

## AppleJwtVerifier Service Design

```php
namespace App\Sync\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleJwtVerifier
{
    private const JWKS_URL = 'https://appleid.apple.com/auth/keys';
    private const CACHE_KEY = 'apple_jwks';
    private const CACHE_TTL = 86400; // 24 hours

    public function verify(string $identityToken): array
    {
        $keys = $this->getKeys();
        $decoded = JWT::decode($identityToken, JWK::parseKeySet($keys));

        // Verify audience matches our Apple app bundle ID
        $expectedAudience = config('services.apple.client_id');
        if ($decoded->aud !== $expectedAudience) {
            throw new \UnexpectedValueException('Invalid audience');
        }

        return [
            'email' => $decoded->email ?? null,
            'name' => $decoded->name ?? null,
            'sub' => $decoded->sub,
        ];
    }

    private function getKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Http::get(self::JWKS_URL)->json();
        });
    }
}
```

## Testing Strategy

- Feature tests for each endpoint (register, login, Google auth, Apple auth)
- Mock `GoogleJwtVerifier` and `AppleJwtVerifier` in tests to avoid real JWKS calls
- Test backward-compatible login with username format
- Test email migration flow (legacy user gets email updated)
- Test conflict case (email already taken during migration → 409)
- Test validation errors (missing fields, invalid email, short password)
