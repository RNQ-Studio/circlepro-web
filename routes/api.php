<?php

use App\Http\Controllers\Api\UserExcelController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\Admin\RevenueController;
use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\ArcheryRangeController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClubAttendanceController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\ClubScheduleController;
use App\Http\Controllers\Api\V1\CoachController;
use App\Http\Controllers\Api\V1\CoachReviewController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EquipmentProfileController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventRegistrationController;
use App\Http\Controllers\Api\V1\EventScoringController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MonetizationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RatingController;
use App\Http\Controllers\Api\V1\ScoringSessionClaimController;
use App\Http\Controllers\Api\V1\ScoringSessionController;
use App\Http\Controllers\Api\V1\ScoringSessionGroupController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TargetFaceController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Excel Import & Export Routes (Tanpa Middleware Auth)
    |--------------------------------------------------------------------------
    | Jika Anda ingin memproteksi endpoint ini dengan middleware auth Passport di kemudian hari,
    | Anda bisa menambahkan middleware: ->middleware('auth:api')
    */
    Route::get('users/export', [UserExcelController::class, 'export']);
    Route::post('users/import', [UserExcelController::class, 'import']);

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
        Route::post('register', [AuthController::class, 'register'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('login', [AuthController::class, 'login'])->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware(['throttle:6,1', 'check.maintenance']);
        // Social sign-in (Google/Apple) — structure ready, verification deferred (task 2.1a)
        Route::post('social', [SocialAuthController::class, 'authenticate'])->middleware(['throttle:10,1', 'check.maintenance']);
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware(['throttle:6,1', 'check.maintenance']);
        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware(['throttle:6,1', 'check.maintenance']);
        Route::get('password/reset/{token}', function (string $token) {
            return ApiResponse::success(['token' => $token], 'Password reset token received.');
        })->name('password.reset')->middleware(['check.maintenance']);

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify')
            ->middleware(['check.maintenance']);

        Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
            Route::post('email/send-verification', [EmailVerificationController::class, 'sendVerification'])
                ->middleware('throttle:6,1');
            Route::post('email/verify', [EmailVerificationController::class, 'verify']);

            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);
            Route::post('avatar', [AuthController::class, 'uploadAvatar']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('phone', [OtpController::class, 'updatePhone']);
            Route::post('phone/verify', [OtpController::class, 'verifyPhone']);
        });
    });

    Route::apiResource('quotes', QuoteController::class)->only(['index', 'show']);

    // Public leaderboard & rating lookup routes
    Route::get('leaderboard', [RatingController::class, 'getLeaderboard']);
    Route::get('users/{user}/ratings', [RatingController::class, 'getUserRatings']);
    Route::get('users/{user}/ratings/{rating}/history', [RatingController::class, 'getRatingHistory']);

    Route::middleware(['auth:api', 'check.maintenance'])->group(function (): void {
        // Quote love/unlove (authenticated)
        Route::post('quotes/{quote}/love', [QuoteController::class, 'love']);
        Route::delete('quotes/{quote}/love', [QuoteController::class, 'unlove']);

        Route::post('assets/upload', [AssetController::class, 'upload'])->middleware('throttle:30,1');

        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('articles', ArticleController::class);
        Route::apiResource('tags', TagController::class);
        Route::apiResource('ranges', ArcheryRangeController::class);

        Route::prefix('notifications')->group(function (): void {
            // Per-category preferences (ManahPro, task 2.5)
            Route::get('preferences', [NotificationPreferenceController::class, 'index']);
            Route::put('preferences', [NotificationPreferenceController::class, 'update']);

            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{notification}/read', [NotificationController::class, 'markRead']);
        });

        /*
        |----------------------------------------------------------------------
        | ManahPro — Module 1 (TRACK / Scoring) — offline-first
        |----------------------------------------------------------------------
        */
        Route::prefix('scoring')->group(function (): void {
            Route::apiResource('equipment-profiles', EquipmentProfileController::class);

            Route::get('target-faces', [TargetFaceController::class, 'index']);
            Route::get('bow-classes', [TargetFaceController::class, 'bowClasses']);

            Route::get('dashboard', [ScoringSessionController::class, 'dashboard']);
            Route::post('sessions/sync', [ScoringSessionController::class, 'sync'])
                ->middleware('throttle:60,1');
            Route::get('sessions/{scoringSession}/summary', [ScoringSessionController::class, 'summary']);
            Route::apiResource('sessions', ScoringSessionController::class)
                ->parameters(['sessions' => 'scoringSession']);

            /*
            |------------------------------------------------------------------
            | Latihan Bersama (group scoring) — Phase 0 (Sprint 02 lifecycle,
            | Sprint 03 score input + offline sync + fair leaderboard)
            |------------------------------------------------------------------
            */
            Route::prefix('groups')->group(function (): void {
                Route::get('lookup', [ScoringSessionGroupController::class, 'lookup']);
                Route::get('/', [ScoringSessionGroupController::class, 'index']);
                Route::post('/', [ScoringSessionGroupController::class, 'store'])
                    ->middleware('throttle:30,1');
                Route::get('{group}', [ScoringSessionGroupController::class, 'show']);
                Route::patch('{group}', [ScoringSessionGroupController::class, 'update']);

                // Sprint 10 — self-join via link/QR/code (Phase 1).
                Route::post('{group}/join', [ScoringSessionGroupController::class, 'join'])
                    ->middleware('throttle:30,1');

                Route::post('{group}/participants', [ScoringSessionGroupController::class, 'addParticipants']);
                Route::delete('{group}/participants/{session}', [ScoringSessionGroupController::class, 'removeParticipant']);

                // Sprint 03 — offline-first scoring & fair leaderboard.
                Route::put('{group}/participants/{session}/score', [ScoringSessionGroupController::class, 'scoreParticipant']);
                Route::post('{group}/sync', [ScoringSessionGroupController::class, 'sync'])
                    ->middleware('throttle:60,1');
                Route::get('{group}/leaderboard', [ScoringSessionGroupController::class, 'leaderboard']);

                // Sprint 13 — guest-slot claim ("Ini Saya") + host inbox (Phase 2).
                Route::post('{group}/participants/{session}/claim', [ScoringSessionClaimController::class, 'store'])
                    ->middleware('throttle:30,1');
                Route::get('{group}/claims', [ScoringSessionClaimController::class, 'index']);
            });

            // Sprint 13 — resolve (host) / cancel (claimant) a guest-slot claim.
            Route::prefix('claims')->group(function (): void {
                Route::patch('{claim}', [ScoringSessionClaimController::class, 'update']);
                Route::delete('{claim}', [ScoringSessionClaimController::class, 'destroy']);
            });
        });

        /*
        |----------------------------------------------------------------------
        | ManahPro — Phase 2: Identity & Social
        |----------------------------------------------------------------------
        */
        // Profile (Module 0/2, task 2.2)
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::get('users/{user}/profile', [ProfileController::class, 'showPublic']);

        // Gamification (Phase 4, tasks 4.9 & 4.10)
        Route::get('gamification/stats', [GamificationController::class, 'stats']);

        // Follow system (Phase 4, tasks 4.1 & 4.2)
        Route::post('users/{user}/follow', [FollowController::class, 'follow']);
        Route::post('users/{user}/unfollow', [FollowController::class, 'unfollow']);
        Route::get('users/{user}/followers', [FollowController::class, 'followers']);
        Route::get('users/{user}/following', [FollowController::class, 'following']);

        // Coaches (Phase 4, task 4.4)
        Route::get('coaches', [CoachController::class, 'index']);
        Route::post('coaches', [CoachController::class, 'store']);
        Route::get('coaches/{coach}', [CoachController::class, 'show']);
        Route::put('coaches/{coach}', [CoachController::class, 'update']);
        Route::get('coaches/{coach}/reviews', [CoachReviewController::class, 'index']);
        Route::post('coaches/{coach}/reviews', [CoachReviewController::class, 'store']);

        // Clubs = organizations type=club (Module 0, task 2.7)
        Route::prefix('clubs')->group(function (): void {
            Route::get('/', [ClubController::class, 'index']);
            Route::post('/', [ClubController::class, 'store'])->middleware('throttle:20,1');
            Route::get('mine', [ClubController::class, 'mine']);
            Route::get('{club}', [ClubController::class, 'show']);
            Route::put('{club}', [ClubController::class, 'update']);
            Route::post('{club}/join', [ClubController::class, 'join']);
            Route::post('{club}/leave', [ClubController::class, 'leave']);
            Route::get('{club}/members', [ClubController::class, 'members']);
            Route::delete('{club}/members/{user}', [ClubController::class, 'removeMember']);
            Route::put('{club}/members/{user}/role', [ClubController::class, 'updateRole']);
            Route::get('{club}/activity', [ClubController::class, 'activity']);

            // Schedules & Attendances (Phase 4, task 4.3)
            Route::get('{club}/schedules', [ClubScheduleController::class, 'index']);
            Route::post('{club}/schedules', [ClubScheduleController::class, 'store']);
            Route::get('{club}/schedules/{schedule}', [ClubScheduleController::class, 'show']);
            Route::put('{club}/schedules/{schedule}', [ClubScheduleController::class, 'update']);
            Route::delete('{club}/schedules/{schedule}', [ClubScheduleController::class, 'destroy']);
            Route::get('{club}/schedules/{schedule}/attendance', [ClubAttendanceController::class, 'index']);
            Route::post('{club}/schedules/{schedule}/attendance', [ClubAttendanceController::class, 'store']);
            Route::get('{club}/my-attendance', [ClubAttendanceController::class, 'myAttendance']);
        });

        // Community feed (Module 5, task 2.11)
        Route::get('posts', [PostController::class, 'index']);
        Route::post('posts', [PostController::class, 'store'])->middleware('throttle:30,1');
        Route::get('posts/{post}', [PostController::class, 'show']);
        Route::delete('posts/{post}', [PostController::class, 'destroy']);
        Route::post('posts/{post}/like', [PostController::class, 'like']);
        Route::delete('posts/{post}/like', [PostController::class, 'unlike']);
        Route::get('posts/{post}/comments', [CommentController::class, 'index']);
        Route::post('posts/{post}/comments', [CommentController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
        Route::post('polls/{poll}/vote', [PostController::class, 'vote']);

        // Story system (Instagram-style)
        Route::get('stories', [StoryController::class, 'index']);
        Route::post('stories', [StoryController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('stories/{story}', [StoryController::class, 'destroy']);
        Route::post('stories/{story}/view', [StoryController::class, 'markAsViewed']);
        Route::get('stories/{story}/viewers', [StoryController::class, 'viewers']);

        /*
        |----------------------------------------------------------------------
        | ManahPro — Phase 3: Events & Ranking (Event Foundation)
        |----------------------------------------------------------------------
        */
        Route::get('my-events', [EventController::class, 'myEvents']);
        Route::apiResource('events', EventController::class);

        Route::get('my-tickets', [EventRegistrationController::class, 'myTickets']);
        Route::post('events/{event}/register', [EventRegistrationController::class, 'register']);
        Route::get('events/{event}/participants', [EventRegistrationController::class, 'participants']);
        Route::post('registrations/{registration}/check-in', [EventRegistrationController::class, 'checkIn']);
        Route::put('registrations/{registration}/status', [EventRegistrationController::class, 'updateStatus']);

        // Live Scoring & Leaderboard routes
        Route::post('events/{event}/assign-targets', [EventScoringController::class, 'assignTargets']);
        Route::get('events/{event}/divisions/{division}/targets/{target_butt}/scorecard', [EventScoringController::class, 'getTargetScorecard']);
        Route::post('events/{event}/divisions/{division}/targets/{target_butt}/ends/{end_number}', [EventScoringController::class, 'saveEndScores']);
        Route::get('events/{event}/divisions/{division}/leaderboard', [EventScoringController::class, 'getLeaderboard']);

        // Glicko-2 Rating calculation & private rating routes
        Route::post('events/{event}/divisions/{division}/finalize-ratings', [RatingController::class, 'finalizeRatings']);
        Route::get('my-ratings', [RatingController::class, 'getMyRatings']);

        // Monetization routes
        Route::get('monetization/plans', [MonetizationController::class, 'plans']);
        Route::get('monetization/subscription', [MonetizationController::class, 'subscription']);
        Route::post('monetization/subscribe/google', [MonetizationController::class, 'subscribeGooglePlay']);
        Route::post('monetization/subscribe/manual', [MonetizationController::class, 'subscribeManual']);
        Route::post('clubs/{club}/subscription', [MonetizationController::class, 'clubSubscription']);

        // Ads routes
        Route::get('ads', [AdController::class, 'index']);
        Route::post('ads/{ad}/click', [AdController::class, 'click']);

        // Admin revenue routes
        Route::get('admin/revenue', [RevenueController::class, 'index']);
    });
});
