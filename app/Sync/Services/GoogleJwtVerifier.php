<?php

namespace App\Sync\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GoogleJwtVerifier
{
    private const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const string CACHE_KEY = 'google_jwks';

    private const int CACHE_TTL = 86400; // 24 hours

    /**
     * Verify the Google ID token and return its payload.
     *
     * @return array{email: string, name: ?string, sub: string}
     *
     * @throws UnexpectedValueException
     */
    public function verify(string $idToken): array
    {
        $keys = $this->getKeys();
        $decoded = JWT::decode($idToken, JWK::parseKeySet($keys));

        $expectedAudience = config('services.google.client_id');
        if ($decoded->aud !== $expectedAudience) {
            throw new UnexpectedValueException('Invalid audience');
        }

        return [
            'email' => $decoded->email,
            'name' => $decoded->name ?? null,
            'sub' => $decoded->sub,
        ];
    }

    /**
     * Get Google JWKS keys, cached for 24 hours.
     */
    private function getKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return Http::get(self::JWKS_URL)->json();
        });
    }
}
