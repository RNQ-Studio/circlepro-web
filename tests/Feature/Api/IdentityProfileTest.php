<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\SocialAuth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class IdentityProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Needed for the Google sign-in flow which issues a real Passport token.
        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);
    }

    public function test_user_can_get_and_update_profile_with_age_group(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.stats.total_sessions', 0);

        $this->putJson('/api/v1/profile', [
            'full_name' => 'Andi Pemanah',
            'bio' => 'Recurve enthusiast',
            'primary_bow_class' => 'compound',
            'birth_date' => '2010-01-01',
            'city' => 'Bandung',
        ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Andi Pemanah')
            ->assertJsonPath('data.primary_bow_class', 'compound')
            ->assertJsonPath('data.city', 'Bandung')
            ->assertJsonPath('data.age_group', 'sma'); // born 2010 → ~16y
    }

    public function test_user_can_view_another_users_public_profile(): void
    {
        $other = User::factory()->create(['name' => 'Budi']);
        Passport::actingAs(User::factory()->create());

        $this->getJson("/api/v1/users/{$other->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.id', $other->id);
    }

    public function test_notification_preferences_defaults_and_update(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.push_enabled', true);

        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                ['category' => 'social', 'push_enabled' => false, 'email_enabled' => true],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'category' => 'social',
            'push_enabled' => false,
            'email_enabled' => true,
        ]);
    }

    public function test_google_sign_in_is_not_configured_without_client_id(): void
    {
        // No GOOGLE_CLIENT_ID in the test env → the real verifier reports
        // "not configured".
        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'token' => 'fake'])
            ->assertStatus(501)
            ->assertJson(['code' => 'SOCIAL_AUTH_NOT_CONFIGURED']);
    }

    public function test_apple_sign_in_is_not_configured(): void
    {
        $this->postJson('/api/v1/auth/social', ['provider' => 'apple', 'token' => 'fake'])
            ->assertStatus(501)
            ->assertJson(['code' => 'SOCIAL_AUTH_NOT_CONFIGURED']);
    }

    public function test_google_sign_in_creates_user_and_issues_token(): void
    {
        // Inject a fake verifier so we don't hit Google in tests.
        $this->app->bind(GoogleIdTokenVerifier::class, fn () => new class implements GoogleIdTokenVerifier
        {
            public function verify(string $idToken): array
            {
                return [
                    'sub' => 'google-123',
                    'email' => 'atlet@example.com',
                    'name' => 'Atlet Google',
                    'email_verified' => true,
                    'picture' => 'https://lh3.googleusercontent.com/a/mock-profile-pic',
                ];
            }
        });

        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'token' => 'valid'])
            ->assertOk()
            ->assertJsonPath('data.is_new', true)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('users', [
            'email' => 'atlet@example.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/mock-profile-pic',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'avatar_url' => 'https://lh3.googleusercontent.com/a/mock-profile-pic',
        ]);
        $this->assertDatabaseHas('user_auth_providers', ['provider' => 'google', 'provider_uid' => 'google-123']);

        // Second sign-in links to the same user (not a new account).
        $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'token' => 'valid'])
            ->assertOk()
            ->assertJsonPath('data.is_new', false);

        $this->assertSame(1, User::query()->where('email', 'atlet@example.com')->count());

        // Verify resource output for /me
        $user = User::query()->where('email', 'atlet@example.com')->firstOrFail();
        $this->actingAs($user, 'api');
        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', 'https://lh3.googleusercontent.com/a/mock-profile-pic');

        // Verify resource output for public profile
        $this->getJson("/api/v1/users/{$user->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.avatar_url', 'https://lh3.googleusercontent.com/a/mock-profile-pic');
    }
}
