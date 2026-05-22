<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class DeviceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['email' => 'device@example.com']);
    }

    public function test_login_without_device_info_works_normally(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'device@example.com',
            'password' => 'password',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('user_devices', 0);
    }

    public function test_login_with_device_info_stores_device(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'device@example.com',
            'password' => 'password',
            'device_id' => 'device-uuid-1234',
            'platform' => 'android',
            'os_version' => 'Android 14',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 8',
            'push_token' => 'fcm-token-abc',
        ])->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->getKey(),
            'device_id' => 'device-uuid-1234',
            'platform' => 'android',
            'os_version' => 'Android 14',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 8',
            'push_token' => 'fcm-token-abc',
        ]);
    }

    public function test_login_from_same_device_upserts_not_duplicates(): void
    {
        $payload = [
            'email' => 'device@example.com',
            'password' => 'password',
            'device_id' => 'same-device-id',
            'platform' => 'ios',
            'app_version' => '1.0.0',
        ];

        $this->postJson('/api/v1/auth/login', $payload)->assertOk();
        $this->postJson('/api/v1/auth/login', array_merge($payload, ['app_version' => '1.1.0']))->assertOk();

        $this->assertDatabaseCount('user_devices', 1);
        $this->assertDatabaseHas('user_devices', ['app_version' => '1.1.0']);
    }

    public function test_login_validates_platform_enum(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'device@example.com',
            'password' => 'password',
            'device_id' => 'some-id',
            'platform' => 'windows',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_logout_with_device_id_nullifies_push_token(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'logout-device',
            'platform' => 'android',
            'push_token' => 'fcm-token-to-remove',
            'last_active_at' => now(),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'device@example.com',
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout', ['device_id' => 'logout-device'])
            ->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'logout-device',
            'push_token' => null,
        ]);
    }

    public function test_logout_without_device_id_does_not_affect_devices(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'keep-token-device',
            'platform' => 'ios',
            'push_token' => 'fcm-token-kept',
            'last_active_at' => now(),
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'device@example.com',
            'password' => 'password',
        ])->json('data.access_token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseHas('user_devices', [
            'device_id' => 'keep-token-device',
            'push_token' => 'fcm-token-kept',
        ]);
    }

    public function test_scope_with_push_token_filters_correctly(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'with-token',
            'platform' => 'android',
            'push_token' => 'fcm-abc',
            'last_active_at' => now(),
        ]);

        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'without-token',
            'platform' => 'ios',
            'push_token' => null,
            'last_active_at' => now(),
        ]);

        $this->assertSame(1, UserDevice::withPushToken()->count());
        $this->assertSame('fcm-abc', UserDevice::withPushToken()->first()->push_token);
    }
}
