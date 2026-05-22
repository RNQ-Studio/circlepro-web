<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;

class AuthService
{
    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws AuthenticationException|AuthorizationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->is_active) {
            throw new AuthorizationException('Your account is inactive.');
        }

        return $this->issueToken([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws AuthenticationException
     */
    public function refresh(string $refreshToken): array
    {
        return $this->issueToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function logout(User $user): void
    {
        $accessToken = $user->token();

        if (! $accessToken instanceof AccessToken) {
            return;
        }

        $tokenId = $accessToken->toArray()['oauth_access_token_id'] ?? null;

        if ($tokenId !== null) {
            RefreshToken::query()
                ->where('access_token_id', $tokenId)
                ->update(['revoked' => true]);
        }

        $accessToken->revoke();
    }

    /**
     * @param  array<string, string>  $params
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws AuthenticationException
     */
    private function issueToken(array $params): array
    {
        $params = [
            'client_id' => (string) config('passport.password_client.id'),
            'client_secret' => (string) config('passport.password_client.secret'),
            'scope' => '',
            ...$params,
        ];

        $request = Request::create('/oauth/token', 'POST', $params);
        $response = app()->handle($request);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true) ?: [];

        if ($response->getStatusCode() !== 200) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => (string) $data['refresh_token'],
            'token_type' => (string) $data['token_type'],
            'expires_in' => (int) $data['expires_in'],
        ];
    }
}
