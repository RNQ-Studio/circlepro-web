<?php

namespace Tests\Feature\Api;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\SmsInterface;
use App\Support\Enums\OtpPurpose;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        // Personal access client is required for createToken() used in OTP login
        app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access', 'users');

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create(['phone' => '+628123456789']);

        $this->accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ])->json('data.access_token');
    }

    // ── OTP generation ───────────────────────────────────────────────────────

    public function test_send_otp_returns_success(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+628123456789',
            'purpose' => 'verify_phone',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_send_otp_validates_purpose_enum(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+628123456789',
            'purpose' => 'invalid_purpose',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose']);
    }

    public function test_otp_code_is_stored_hashed(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+628123456789',
            'purpose' => 'verify_phone',
        ])->assertOk();

        $otp = OtpCode::first();
        $this->assertNotNull($otp);
        // Code should be hashed — Bcrypt/Argon hashes are much longer than 6 digits
        $this->assertGreaterThan(6, strlen($otp->code));
    }

    public function test_sending_new_otp_invalidates_previous_one(): void
    {
        $sms = $this->mock(SmsInterface::class);
        $sms->shouldReceive('send')->twice()->andReturn(true);

        $service = app(OtpService::class);
        $service->generate('+628123456789', OtpPurpose::VerifyPhone);
        $service->generate('+628123456789', OtpPurpose::VerifyPhone);

        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_rate_limit_blocks_after_3_requests(): void
    {
        Cache::flush();

        $sms = $this->mock(SmsInterface::class);
        $sms->shouldReceive('send')->times(3)->andReturn(true);

        $service = app(OtpService::class);
        $service->generate('+62999', OtpPurpose::VerifyPhone);
        $service->generate('+62999', OtpPurpose::VerifyPhone);
        $service->generate('+62999', OtpPurpose::VerifyPhone);

        $this->expectException(\RuntimeException::class);
        $service->generate('+62999', OtpPurpose::VerifyPhone);
    }

    // ── OTP verification ─────────────────────────────────────────────────────

    public function test_verify_otp_with_correct_code_returns_success(): void
    {
        $plainCode = '123456';

        OtpCode::create([
            'phone' => '+628123456789',
            'code' => Hash::make($plainCode),
            'purpose' => OtpPurpose::VerifyPhone,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+628123456789',
            'code' => $plainCode,
            'purpose' => 'verify_phone',
        ])->assertOk()->assertJson(['success' => true]);
    }

    public function test_verify_otp_with_wrong_code_returns_422(): void
    {
        OtpCode::create([
            'phone' => '+628123456789',
            'code' => Hash::make('654321'),
            'purpose' => OtpPurpose::VerifyPhone,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+628123456789',
            'code' => '000000',
            'purpose' => 'verify_phone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_verify_expired_otp_returns_422(): void
    {
        OtpCode::create([
            'phone' => '+628123456789',
            'code' => Hash::make('123456'),
            'purpose' => OtpPurpose::VerifyPhone,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+628123456789',
            'code' => '123456',
            'purpose' => 'verify_phone',
        ])->assertUnprocessable();
    }

    public function test_used_otp_cannot_be_verified_twice(): void
    {
        $plainCode = '123456';

        OtpCode::create([
            'phone' => '+628123456789',
            'code' => Hash::make($plainCode),
            'purpose' => OtpPurpose::VerifyPhone,
            'expires_at' => now()->addMinutes(5),
            'used_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+628123456789',
            'code' => $plainCode,
            'purpose' => 'verify_phone',
        ])->assertUnprocessable();
    }

    public function test_login_via_otp_returns_access_token(): void
    {
        $plainCode = '123456';

        OtpCode::create([
            'phone' => '+628123456789',
            'code' => Hash::make($plainCode),
            'purpose' => OtpPurpose::Login,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+628123456789',
            'code' => $plainCode,
            'purpose' => 'login',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
    }

    // ── Phone update & verify ─────────────────────────────────────────────────

    public function test_user_can_update_phone_number(): void
    {
        $sms = $this->mock(SmsInterface::class);
        $sms->shouldReceive('send')->once()->andReturn(true);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/phone', ['phone' => '+6281999000111'])
            ->assertOk();

        $this->user->refresh();
        $this->assertSame('+6281999000111', $this->user->phone);
        $this->assertNull($this->user->phone_verified_at);
    }

    public function test_user_can_verify_phone_with_correct_otp(): void
    {
        $plainCode = '999888';

        OtpCode::create([
            'phone' => $this->user->phone,
            'code' => Hash::make($plainCode),
            'purpose' => OtpPurpose::VerifyPhone,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/phone/verify', ['code' => $plainCode])
            ->assertOk();

        $this->user->refresh();
        $this->assertNotNull($this->user->phone_verified_at);
    }
}
