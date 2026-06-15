<?php

namespace App\Sync\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class AppleJwtVerifier
{
    private const string JWKS_URL = 'https://appleid.apple.com/auth/keys';

    private const string CACHE_KEY = 'apple_jwks';

    private const int CACHE_TTL = 86400; // 24 hours

    /**
     * Verify the Apple identity token and return its payload.
     *
     * @return array{email: ?string, name: ?string, sub: string}
     *
     * @throws UnexpectedValueException
     */
    public function verify(string $identityToken): array
    {
        $keys = $this->getKeys();
        $decoded = JWT::decode($identityToken, JWK::parseKeySet($keys));

        $expectedAudience = config('services.apple.client_id');
        if ($decoded->aud !== $expectedAudience) {
            throw new UnexpectedValueException('Invalid audience');
        }

        return [
            'email' => $decoded->email ?? null,
            'name' => $decoded->name ?? null,
            'sub' => $decoded->sub,
        ];
    }

    /**
     * Get Apple JWKS keys, cached for 24 hours.
     */
    private function getKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return Http::get(self::JWKS_URL)->json();
        });
    }
}
