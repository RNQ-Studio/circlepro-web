<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserStat;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GamificationController extends Controller
{
    /**
     * Get the user's gamification stats and badge unlock status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Retrieve or initialize stats
        /** @var UserStat $stats */
        $stats = $user->stats ?? UserStat::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'xp' => 0,
                'level' => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
            ]
        );

        // Fetch user's unlocked badge mappings
        $unlockedBadges = \DB::table('user_badges')
            ->where('user_id', $user->id)
            ->pluck('unlocked_at', 'badge_id')
            ->toArray();

        // Get all badges and flag if unlocked
        $allBadges = Badge::query()->get()->map(function ($badge) use ($unlockedBadges) {
            $isUnlocked = array_key_exists($badge->id, $unlockedBadges);
            return [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon_code' => $badge->icon_code,
                'requirement_type' => $badge->requirement_type,
                'requirement_value' => $badge->requirement_value,
                'unlocked' => $isUnlocked,
                'unlocked_at' => $isUnlocked ? Carbon::parse($unlockedBadges[$badge->id])->toIso8601String() : null,
            ];
        });

        return ApiResponse::success([
            'xp' => $stats->xp,
            'level' => $stats->level,
            'current_streak' => $stats->current_streak,
            'longest_streak' => $stats->longest_streak,
            'last_session_at' => $stats->last_session_at?->toIso8601String(),
            'badges' => $allBadges,
        ]);
    }
}
