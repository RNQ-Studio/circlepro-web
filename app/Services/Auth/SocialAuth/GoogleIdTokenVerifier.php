<?php

namespace App\Services\Auth\SocialAuth;

use Illuminate\Auth\AuthenticationException;

interface GoogleIdTokenVerifier
{
    /**
     * Verify a Google ID token and return its claims.
     *
     * @return array{sub: string, email: string|null, name: string|null, email_verified: bool}
     *
     * @throws SocialAuthNotConfiguredException when no client_id is configured
     * @throws AuthenticationException when the token is invalid/untrusted
     */
    public function verify(string $idToken): array;
}
