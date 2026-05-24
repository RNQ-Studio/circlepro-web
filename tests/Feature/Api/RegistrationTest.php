<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Support\Enums\DevicePlatform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_user_can_register_successfully_and_receives_tokens(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Registration successful'])
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'email_verified'],
            ])
            ->assertJsonPath('data.email_verified', false);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'is_active' => true,
        ]);

        $user = User::where('email', 'johndoe@example.com')->firstOrFail();

        // Assert that the email verification notification was sent
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_fails_validation_errors(): void
    {
        // 1. Missing fields
        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // 2. Short / Mismatched password
        $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 3. Duplicate email
        User::factory()->create(['email' => 'duplicate@example.com']);
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'duplicate@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_registers_device_and_associates_with_user(): void
    {
        Notification::fake();

        $payload = [
            'name' => 'Device User',
            'email' => 'deviceuser@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'device_id' => 'my-unique-device-id',
            'platform' => 'android',
            'os_version' => '13.0',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 7',
            'push_token' => 'fcm-push-token-123',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201);

        $user = User::where('email', 'deviceuser@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'device_id' => 'my-unique-device-id',
            'platform' => 'android',
            'os_version' => '13.0',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 7',
            'push_token' => 'fcm-push-token-123',
        ]);
    }
}
