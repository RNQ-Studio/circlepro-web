<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Sms\SmsInterface;
use App\Support\Enums\OtpPurpose;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    private const TTL_MINUTES = 5;

    private const MAX_REQUESTS_PER_WINDOW = 3;

    private const RATE_WINDOW_MINUTES = 10;

    public function __construct(private readonly SmsInterface $sms) {}

    /**
     * Generate an OTP, persist it hashed, and dispatch via SMS.
     *
     * @throws \RuntimeException when rate limit is exceeded
     */
    public function generate(string $phone, OtpPurpose $purpose, ?string $ip = null): OtpCode
    {
        $this->enforceRateLimit($phone);

        // Invalidate any unused previous OTPs for same phone + purpose
        OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose->value)
            ->whereNull('used_at')
            ->delete();

        $plainCode = (string) random_int(100000, 999999);

        $otp = OtpCode::create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
        ]);

        $this->sms->send($phone, "Your verification code is: {$plainCode}. Valid for ".self::TTL_MINUTES.' minutes.');

        return $otp;
    }

    /**
     * Verify an OTP code. Returns true and marks the OTP as used on success.
     */
    public function verify(string $phone, string $plainCode, OtpPurpose $purpose): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose->value)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($otp === null || $otp->isExpired() || ! Hash::check($plainCode, $otp->code)) {
            return false;
        }

        $otp->update(['used_at' => now()]);

        return true;
    }

    private function enforceRateLimit(string $phone): void
    {
        $key = 'otp_rate:'.$phone;
        $count = (int) Cache::get($key, 0);

        if ($count >= self::MAX_REQUESTS_PER_WINDOW) {
            throw new \RuntimeException('Too many OTP requests. Please wait before trying again.');
        }

        Cache::put($key, $count + 1, now()->addMinutes(self::RATE_WINDOW_MINUTES));
    }
}
