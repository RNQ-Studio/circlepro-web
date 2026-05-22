<?php

use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', HealthController::class);

    // Unauthenticated app info endpoints (no maintenance check — needed to show maintenance message)
    Route::prefix('app')->group(function (): void {
        Route::get('version', [AppController::class, 'version'])->middleware('throttle:60,1');
        Route::get('config', [AppController::class, 'config'])->middleware('throttle:60,1');
    });

    // OTP endpoints (unauthenticated, heavily throttled)
    Route::prefix('auth/otp')->middleware(['throttle:10,1', 'check.maintenance'])->group(function (): void {
        Route::post('send', [OtpController::class, 'send']);
        Route::post('verify', [OtpController::class, 'verify']);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['throttle:6,1', 'check.maintenance']);

        Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);
            Route::post('avatar', [AuthController::class, 'uploadAvatar']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('phone', [OtpController::class, 'updatePhone']);
            Route::post('phone/verify', [OtpController::class, 'verifyPhone']);
        });
    });

    Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
        Route::apiResource('categories', CategoryController::class);

        Route::prefix('notifications')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{notification}/read', [NotificationController::class, 'markRead']);
        });
    });
});
