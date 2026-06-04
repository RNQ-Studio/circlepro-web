<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase wipes oauth_clients, so issue a fresh password-grant
        // client per test and point the config at it.
        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['email' => 'tester@example.com']);
        $this->user->assignRole('admin');
    }

    public function test_login_returns_tokens(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Login successful'])
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'email_verified'],
            ])
            ->assertJsonPath('data.email_verified', true);
    }

    public function test_login_returns_email_verified_false_when_unverified(): void
    {
        $unverifiedUser = User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJson(['success' => false]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
        ])->assertForbidden()->assertJson(['success' => false]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_me_returns_authenticated_user_with_roles(): void
    {
        $token = $this->loginToken();

        $this->withToken($token['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'tester@example.com')
            ->assertJsonPath('data.roles', ['admin']);
    }

    public function test_refresh_returns_a_new_access_token(): void
    {
        $token = $this->loginToken();

        $refreshed = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $token['refresh_token'],
        ])->assertOk()->json('data');

        $this->assertNotSame($token['access_token'], $refreshed['access_token']);
        $this->assertArrayHasKey('refresh_token', $refreshed);
    }

    public function test_logout_revokes_the_access_token(): void
    {
        $token = $this->loginToken();

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $this->user->getKey(),
            'revoked' => false,
        ]);

        $this->withToken($token['access_token'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        // The token is revoked in storage, so it can no longer authenticate.
        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $this->user->getKey(),
            'revoked' => true,
        ]);
        $this->assertDatabaseMissing('oauth_access_tokens', [
            'user_id' => $this->user->getKey(),
            'revoked' => false,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function loginToken(): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
        ])->assertOk()->json('data');
    }

    public function test_login_throws_runtime_exception_when_passport_misconfigured_in_debug_mode(): void
    {
        $this->withoutExceptionHandling();

        config([
            'app.debug' => true,
            'passport.password_client.secret' => 'incorrect-secret-to-trigger-invalid-client',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Passport configuration error');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
        ]);
    }

    public function test_login_throws_authentication_exception_when_passport_misconfigured_in_production_mode(): void
    {
        config([
            'app.debug' => false,
            'passport.password_client.secret' => 'incorrect-secret-to-trigger-invalid-client',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_logout_all_revokes_all_tokens_and_push_tokens(): void
    {
        // 1. Generate two logins (tokens) for the user
        $tokens1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
            'device_id' => 'device-1',
            'platform' => 'android',
            'push_token' => 'push-token-1',
        ])->json('data');

        $tokens2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'tester@example.com',
            'password' => 'password',
            'device_id' => 'device-2',
            'platform' => 'ios',
            'push_token' => 'push-token-2',
        ])->json('data');

        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $this->user->getKey(),
            'revoked' => false,
        ]);

        // 2. Perform logout-all using the first token
        $this->withToken($tokens1['access_token'])
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged out from all devices',
            ]);

        // Clear in-memory cached authentication guards for subsequent request in same test process
        $this->app['auth']->forgetGuards();

        // Assert BOTH tokens are revoked in the database
        $this->assertDatabaseMissing('oauth_access_tokens', [
            'user_id' => $this->user->getKey(),
            'revoked' => false,
        ]);

        // Assert push tokens are nullified for all devices
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->getKey(),
            'device_id' => 'device-1',
            'push_token' => null,
        ]);
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->getKey(),
            'device_id' => 'device-2',
            'push_token' => null,
        ]);

        // Assert both tokens are unauthorized to access me endpoint
        $this->withToken($tokens1['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withToken($tokens2['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
