<?php

namespace App\Services\Auth\SocialAuth;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Http;

/**
 * Verifies Google ID tokens via Google's public `tokeninfo` endpoint and
 * checks the audience matches our configured OAuth client ID.
 */
class HttpGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');
        if (blank($clientId)) {
            throw new SocialAuthNotConfiguredException;
        }

        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (! $response->ok()) {
            throw new AuthenticationException('Token Google tidak valid.');
        }

        $payload = $response->json();
        if (($payload['aud'] ?? null) !== $clientId) {
            throw new AuthenticationException('Audience token Google tidak cocok.');
        }

        $verified = $payload['email_verified'] ?? false;

        return [
            'sub' => (string) $payload['sub'],
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? null,
            'email_verified' => $verified === true || $verified === 'true',
        ];
    }
}
