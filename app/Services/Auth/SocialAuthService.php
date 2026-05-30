<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserAuthProvider;
use App\Services\Auth\SocialAuth\GoogleIdTokenVerifier;
use App\Support\Enums\AuthProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Social sign-in (ManahPro task 2.1). Verifies a provider ID token, links it
 * to a (new or existing) global user via user_auth_providers, then issues a
 * Passport token through the shared {@see AuthService::issueTokenForUser()}.
 */
class SocialAuthService
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $google,
        private readonly AuthService $auth,
    ) {}

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, is_new: bool}
     *
     * @throws AuthenticationException
     */
    public function loginWithGoogle(string $idToken): array
    {
        $payload = $this->google->verify($idToken);

        if (blank($payload['email'])) {
            throw new AuthenticationException('Akun Google tidak memiliki email.');
        }

        [$user, $isNew] = $this->resolveUser(AuthProvider::Google, $payload);

        return [...$this->auth->issueTokenForUser($user), 'is_new' => $isNew];
    }

    /**
     * Find the user linked to this provider identity, or by email, else create one.
     *
     * @param  array{sub: string, email: string|null, name: string|null, email_verified: bool}  $payload
     * @return array{0: User, 1: bool} the user and whether it was just created
     */
    private function resolveUser(AuthProvider $provider, array $payload): array
    {
        return DB::transaction(function () use ($provider, $payload): array {
            $link = UserAuthProvider::query()
                ->where('provider', $provider->value)
                ->where('provider_uid', $payload['sub'])
                ->first();

            if ($link !== null) {
                return [$link->user, false];
            }

            $user = User::query()->where('email', $payload['email'])->first();
            $isNew = $user === null;

            if ($isNew) {
                $user = User::query()->create([
                    'name' => $payload['name'] ?? 'Pemanah',
                    'full_name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Str::random(40), // hashed by the model cast; unusable for password login
                    'email_verified_at' => $payload['email_verified'] ? now() : null,
                ]);
            }

            UserAuthProvider::query()->create([
                'user_id' => $user->id,
                'provider' => $provider->value,
                'provider_uid' => $payload['sub'],
                'email' => $payload['email'],
            ]);

            return [$user, $isNew];
        });
    }
}
