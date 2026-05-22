<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;

class AuthService
{
    /**
     * @param  array{device_id?: string, platform?: string, os_version?: string|null, app_version?: string|null, device_name?: string|null, push_token?: string|null}  $deviceInfo
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     *
     * @throws AuthenticationException|AuthorizationException
     */
    public function login(string $email, string $password, array $deviceInfo = []): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->is_active) {
            throw new AuthorizationException('Your account is inactive.');
        }

        $tokens = $this->issueToken([
            'grant_type' => 'password',
            'username' => $email,
            'password' => $password,
        ]);

        // Reload user after token issuance to ensure it exists
        if ($user !== null && isset($deviceInfo['device_id'], $deviceInfo['platform'])) {
            $this->upsertDevice($user, $deviceInfo);
        }

        return $tokens;
    }

    /**
     * Issue a Personal Access Token for a user after OTP verification.
     * Unlike the Password Grant, this does not produce a refresh token.
     *
     * @return array{access_token: string, refresh_token: null, token_type: string, expires_in: int}
     */
    public function issueTokenForUser(User $user): array
    {
        $result = $user->createToken('otp-login');
        $expiresAt = $result->token->expires_at ?? now()->addHours(8);

        return [
            'access_token' => $result->accessToken,
            'refresh_token' => null,
            'token_type' => 'Bearer',
            'expires_in' => (int) now()->diffInSeconds($expiresAt),
        ];
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

    public function logout(User $user, ?string $deviceId = null): void
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

        // Nullify push token so device stops receiving notifications
        if ($deviceId !== null) {
            UserDevice::query()
                ->where('user_id', $user->getKey())
                ->where('device_id', $deviceId)
                ->update(['push_token' => null]);
        }
    }

    /**
     * @param  array{device_id: string, platform: string, os_version?: string|null, app_version?: string|null, device_name?: string|null, push_token?: string|null}  $deviceInfo
     */
    private function upsertDevice(User $user, array $deviceInfo): void
    {
        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'device_id' => $deviceInfo['device_id'],
            ],
            [
                'platform' => $deviceInfo['platform'],
                'os_version' => $deviceInfo['os_version'] ?? null,
                'app_version' => $deviceInfo['app_version'] ?? null,
                'device_name' => $deviceInfo['device_name'] ?? null,
                'push_token' => $deviceInfo['push_token'] ?? null,
                'last_active_at' => now(),
            ]
        );
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
