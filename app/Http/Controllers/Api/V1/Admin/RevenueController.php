<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\ApiResponse;
use App\Support\Enums\PaymentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    /** Display MRR, active plans, total users, and total payments */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(['admin', 'super-admin']), 403, 'Admin role required.');

        // 1. Calculate Monthly Recurring Revenue (MRR) from active user/club subscriptions
        $activeSubscriptions = Subscription::where('status', 'active')
            ->with('plan')
            ->get();

        $mrr = 0;
        $activeUserSubs = 0;
        $activeClubSubs = 0;
        $plansBreakdown = [];

        foreach ($activeSubscriptions as $sub) {
            if ($sub->plan) {
                $mrr += $sub->plan->price;
                $plansBreakdown[$sub->plan->name] = ($plansBreakdown[$sub->plan->name] ?? 0) + 1;
            }

            if ($sub->subscriber_type === 'user') {
                $activeUserSubs++;
            } else {
                $activeClubSubs++;
            }
        }

        // 2. Calculate Total Paid Transactions value
        $totalPaidAmount = Payment::where('status', PaymentStatus::Paid->value)->sum('amount');
        $totalTransactionsCount = Payment::where('status', PaymentStatus::Paid->value)->count();

        // 3. Subscription counts by status
        $statusBreakdown = Subscription::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();

        return ApiResponse::success([
            'mrr' => $mrr,
            'active_subscribers' => [
                'total' => $activeSubscriptions->count(),
                'users' => $activeUserSubs,
                'clubs' => $activeClubSubs,
            ],
            'plans_breakdown' => $plansBreakdown,
            'status_breakdown' => $statusBreakdown,
            'revenue' => [
                'total_paid_amount' => (int) $totalPaidAmount,
                'total_transactions_count' => $totalTransactionsCount,
            ],
        ]);
    }
}
