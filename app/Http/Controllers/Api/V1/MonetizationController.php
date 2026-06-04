<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\ScoringSession;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Support\ApiResponse;
use App\Support\Enums\PaymentProvider;
use App\Support\Enums\PaymentStatus;
use App\Support\Enums\PlanAudience;
use App\Support\Enums\SubscriptionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MonetizationController extends Controller
{
    /** List active plans */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success($plans);
    }

    /** User's current subscription details & limits usage */
    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get active subscription
        $sub = Subscription::where('user_id', $user->id)
            ->where('subscriber_type', 'user')
            ->whereIn('status', ['active', 'trialing', 'cancelled', 'past_due'])
            ->with('plan')
            ->first();

        // Count sessions this week
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $sessionsCount = ScoringSession::where('user_id', $user->id)
            ->where('started_at', '>=', $startOfWeek)
            ->count();

        // Determine plan and limit
        if ($sub && $sub->isActive()) {
            $plan = $sub->plan;
            $limits = $plan->limits ?? [];
            $maxSessions = $limits['scoring_per_week'] ?? -1; // -1 means unlimited
        } else {
            // Free plan default limits
            $maxSessions = 3;
            $plan = [
                'code' => 'free',
                'name' => 'Free',
                'price' => 0,
                'interval' => 'monthly',
                'features' => ['3 Latihan / minggu', 'Iklan aktif'],
                'limits' => ['scoring_per_week' => 3],
            ];
        }

        return ApiResponse::success([
            'subscription' => $sub ? [
                'id' => $sub->id,
                'status' => $sub->status,
                'provider' => $sub->provider,
                'current_period_end' => $sub->current_period_end?->toIso8601String(),
                'plan' => $sub->plan,
            ] : null,
            'plan_details' => $plan,
            'usage' => [
                'scoring_sessions_this_week' => $sessionsCount,
                'scoring_sessions_limit' => $maxSessions,
                'is_gated' => $maxSessions !== -1 && $sessionsCount >= $maxSessions,
            ]
        ]);
    }

    /** Mock Google Play purchase validation */
    public function subscribeGooglePlay(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string',
            'purchase_token' => 'required|string',
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('code', $request->plan_code)
            ->where('audience', PlanAudience::User->value)
            ->firstOrFail();

        // 1. Create paid Payment record
        $payment = Payment::create([
            'payment_number' => 'PAY-GP-' . strtoupper(Str::random(10)),
            'user_id' => $user->id,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
            'provider' => PaymentProvider::GooglePlay->value,
            'method' => 'google_play_billing',
            'amount' => $plan->price,
            'fee' => 0,
            'currency' => 'IDR',
            'status' => PaymentStatus::Paid->value,
            'provider_ref' => $request->purchase_token,
            'paid_at' => Carbon::now(),
        ]);

        // 2. Create/Update Subscription
        $subscription = Subscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'subscriber_type' => 'user',
            ],
            [
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
                'provider' => PaymentProvider::GooglePlay->value,
                'provider_subscription_id' => $request->purchase_token,
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addMonth(),
                'cancelled_at' => null,
            ]
        );

        // 3. Create Subscription Invoice
        SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'amount' => $plan->price,
            'period_start' => Carbon::now(),
            'period_end' => Carbon::now()->addMonth(),
            'status' => PaymentStatus::Paid->value,
        ]);

        return ApiResponse::success([
            'subscription' => $subscription->load('plan'),
            'payment' => $payment
        ], 'Subscription successfully validated via Google Play');
    }

    /** Request manual transfer subscription (pending status) */
    public function subscribeManual(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string',
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('code', $request->plan_code)
            ->where('audience', PlanAudience::User->value)
            ->firstOrFail();

        // 1. Create pending Payment
        $payment = Payment::create([
            'payment_number' => 'PAY-TRF-' . strtoupper(Str::random(10)),
            'user_id' => $user->id,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
            'provider' => PaymentProvider::Manual->value,
            'method' => 'bank_transfer',
            'amount' => $plan->price,
            'fee' => 0,
            'currency' => 'IDR',
            'status' => PaymentStatus::Pending->value,
            'expired_at' => Carbon::now()->addDays(1),
        ]);

        // 2. Create Subscription in 'unpaid' state
        $subscription = Subscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'subscriber_type' => 'user',
            ],
            [
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Unpaid->value,
                'provider' => PaymentProvider::Manual->value,
                'provider_subscription_id' => null,
                'current_period_start' => null,
                'current_period_end' => null,
            ]
        );

        // 3. Create Invoice
        SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'amount' => $plan->price,
            'period_start' => Carbon::now(),
            'period_end' => Carbon::now()->addMonth(),
            'status' => PaymentStatus::Pending->value,
        ]);

        return ApiResponse::success([
            'subscription' => $subscription->load('plan'),
            'payment' => $payment,
            'instructions' => 'Silakan transfer ke rekening Bank Mandiri 123-456-7890 a/n CirclePro sebesar Rp' . number_format($plan->price, 0, ',', '.')
        ], 'Manual subscription request created.');
    }

    /** Club Subscription Management */
    public function clubSubscription(Request $request, Organization $club): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string',
        ]);

        // Verify membership role
        $member = $club->members()->where('user_id', $request->user()->id)->first();
        abort_unless($member && in_array($member->role->value, ['owner', 'admin']), 403, 'Club admin/owner role required.');

        $plan = SubscriptionPlan::where('code', $request->plan_code)
            ->where('audience', PlanAudience::Organization->value)
            ->firstOrFail();

        // 1. Create Payment
        $payment = Payment::create([
            'payment_number' => 'PAY-CLUB-' . strtoupper(Str::random(10)),
            'user_id' => $request->user()->id,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
            'provider' => PaymentProvider::Manual->value,
            'method' => 'bank_transfer',
            'amount' => $plan->price,
            'fee' => 0,
            'currency' => 'IDR',
            'status' => PaymentStatus::Paid->value,
            'paid_at' => Carbon::now(),
        ]);

        // 2. Create club subscription
        $subscription = Subscription::updateOrCreate(
            [
                'organization_id' => $club->id,
                'subscriber_type' => 'organization',
            ],
            [
                'subscription_plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active->value,
                'provider' => PaymentProvider::Manual->value,
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addMonth(),
            ]
        );

        // 3. Create invoice
        SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'amount' => $plan->price,
            'period_start' => Carbon::now(),
            'period_end' => Carbon::now()->addMonth(),
            'status' => PaymentStatus::Paid->value,
        ]);

        return ApiResponse::success([
            'subscription' => $subscription->load('plan'),
            'payment' => $payment
        ], 'Club subscription upgraded successfully');
    }
}
