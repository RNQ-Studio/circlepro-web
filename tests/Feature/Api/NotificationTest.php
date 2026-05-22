<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\FcmDriverInterface;
use App\Services\PushNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Passport\ClientRepository;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

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

        $this->user = User::factory()->create();

        $this->token = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('data.access_token');
    }

    // ── API endpoints ────────────────────────────────────────────────────────

    public function test_notifications_list_is_empty_initially(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_notifications_list_returns_users_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->getKey(),
            'title' => 'Hello',
            'body' => 'World',
            'type' => 'system',
        ]);

        $other = User::factory()->create();
        Notification::create([
            'user_id' => $other->getKey(),
            'title' => 'Other',
            'body' => 'User',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unread_count_is_correct(): void
    {
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'A', 'body' => 'B', 'type' => 'system']);
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'C', 'body' => 'D', 'type' => 'system', 'read_at' => now()]);

        $this->withToken($this->token)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->getKey(),
            'title' => 'Test',
            'body' => 'Body',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_another_users_notification_as_read(): void
    {
        $other = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $other->getKey(),
            'title' => 'Other',
            'body' => 'Body',
            'type' => 'system',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();
    }

    public function test_mark_all_notifications_as_read(): void
    {
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'A', 'body' => 'B', 'type' => 'system']);
        Notification::create(['user_id' => $this->user->getKey(), 'title' => 'C', 'body' => 'D', 'type' => 'system']);

        $this->withToken($this->token)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(0, Notification::where('user_id', $this->user->getKey())->unread()->count());
    }

    // ── PushNotificationService ──────────────────────────────────────────────

    public function test_push_notification_is_sent_to_device_with_push_token(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'dev-1',
            'platform' => 'android',
            'push_token' => 'fcm-token-valid',
            'last_active_at' => now(),
        ]);

        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')
            ->once()
            ->with('fcm-token-valid', 'Title', 'Body', [])
            ->andReturn(true);

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->getKey(),
            'title' => 'Title',
        ]);
        $this->assertNotNull(Notification::where('user_id', $this->user->getKey())->first()->sent_at);
    }

    public function test_invalid_fcm_token_is_cleared_after_failed_send(): void
    {
        UserDevice::create([
            'user_id' => $this->user->getKey(),
            'device_id' => 'dev-bad',
            'platform' => 'android',
            'push_token' => 'invalid-token',
            'last_active_at' => now(),
        ]);

        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')->once()->andReturn(false);

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseHas('user_devices', ['device_id' => 'dev-bad', 'push_token' => null]);
        $this->assertNotNull(Notification::where('user_id', $this->user->getKey())->first()->failed_at);
    }

    public function test_push_to_user_with_no_devices_records_notification(): void
    {
        /** @var MockInterface&FcmDriverInterface $mockFcm */
        $mockFcm = $this->mock(FcmDriverInterface::class);
        $mockFcm->shouldReceive('send')->never();

        $service = new PushNotificationService($mockFcm);
        $service->send($this->user, 'Title', 'Body');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull(Notification::first()->sent_at);
    }
}
