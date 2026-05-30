<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuth\SocialAuthNotConfiguredException;
use App\Services\Auth\SocialAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Social sign-in (task 2.1). Google is implemented server-side (ID-token
 * verification → user_auth_providers → Passport token) and activates once
 * GOOGLE_CLIENT_ID is configured. Apple verification is still pending its key.
 * Email + phone OTP login already work via AuthController/OtpController.
 */
class SocialAuthController extends Controller
{
    public function __construct(private readonly SocialAuthService $social) {}

    /**
     * @unauthenticated
     */
    public function authenticate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['google', 'apple'])],
            'token' => ['required', 'string'],
            'device_id' => ['nullable', 'string'],
        ]);

        if ($data['provider'] === 'apple') {
            return ApiResponse::error(
                message: 'Login Apple belum dikonfigurasi. Gunakan Google, email, atau OTP nomor HP.',
                status: 501,
                code: 'SOCIAL_AUTH_NOT_CONFIGURED',
            );
        }

        try {
            $tokens = $this->social->loginWithGoogle($data['token']);
        } catch (SocialAuthNotConfiguredException) {
            return ApiResponse::error(
                message: 'Login Google belum dikonfigurasi. Gunakan email atau OTP nomor HP.',
                status: 501,
                code: 'SOCIAL_AUTH_NOT_CONFIGURED',
            );
        }

        return ApiResponse::success($tokens, 'Login berhasil');
    }
}
