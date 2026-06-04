<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Subscription;
use App\Support\ApiResponse;
use App\Support\Enums\AdStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdController extends Controller
{
    /** Return active ads for placement */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. If user is logged in, check if they have an active subscription (Pro/Elite)
        if ($user) {
            $sub = Subscription::where('user_id', $user->id)
                ->where('subscriber_type', 'user')
                ->whereIn('status', ['active', 'trialing'])
                ->first();

            if ($sub && $sub->isActive()) {
                // Ad-free experience for premium subscribers
                return ApiResponse::success([]);
            }
        }

        // 2. Fetch active ads for the requested placement
        $placement = $request->query('placement', 'feed');

        $ads = Ad::where('placement', $placement)
            ->whereHas('campaign', function ($query) {
                $query->where('status', AdStatus::Active->value)
                    ->where('starts_at', '<=', Carbon::now())
                    ->where('ends_at', '>=', Carbon::now());
            })
            ->inRandomOrder()
            ->limit(5)
            ->get();

        foreach ($ads as $ad) {
            $ad->increment('impression_count');
        }

        return ApiResponse::success($ads);
    }

    /** Log click on an ad */
    public function click(Request $request, Ad $ad): JsonResponse
    {
        $ad->increment('click_count');
        return ApiResponse::success(['click_url' => $ad->click_url], 'Ad click tracked');
    }
}
