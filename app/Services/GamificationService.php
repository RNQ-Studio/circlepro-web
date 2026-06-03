<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserStat;
use Illuminate\Support\Carbon;

class GamificationService
{
    /**
     * Record session completion and reward XP/streaks/badges.
     *
     * @param User $user
     * @param int $arrowCount
     * @param bool $isPersonalBest
     * @return array
     */
    public function recordSessionCompletion(User $user, int $arrowCount, bool $isPersonalBest): array
    {
        // 1. Get or create user stats
        /** @var UserStat $stats */
        $stats = UserStat::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'xp' => 0,
                'level' => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_session_at' => null,
            ]
        );

        $oldLevel = $stats->level;

        // 2. Calculate XP Gains
        $baseXp = 50;
        $arrowXp = 5 * $arrowCount;
        $pbXp = $isPersonalBest ? 100 : 0;
        $totalGainedXp = $baseXp + $arrowXp + $pbXp;

        $stats->xp += $totalGainedXp;

        // 3. Level calculation (every 500 XP is one level)
        $newLevel = floor($stats->xp / 500) + 1;
        $stats->level = (int) $newLevel;

        $levelUp = $newLevel > $oldLevel;

        // 4. Streak calculation
        $now = Carbon::now();
        if ($stats->last_session_at === null) {
            $stats->current_streak = 1;
        } else {
            $lastDate = Carbon::parse($stats->last_session_at)->toDateString();
            $todayDate = $now->toDateString();
            $yesterdayDate = $now->copy()->subDay()->toDateString();

            if ($lastDate === $todayDate) {
                // Streak unchanged if practiced multiple times in the same day
            } elseif ($lastDate === $yesterdayDate) {
                // Practiced consecutive days
                $stats->current_streak += 1;
            } else {
                // Missed a day, reset streak to 1 since we are practicing today
                $stats->current_streak = 1;
            }
        }

        if ($stats->current_streak > $stats->longest_streak) {
            $stats->longest_streak = $stats->current_streak;
        }

        $stats->last_session_at = $now;
        $stats->save();

        // 5. Check and unlock badges
        $unlockedBadgeIds = UserBadge::query()
            ->where('user_id', $user->id)
            ->pluck('badge_id')
            ->toArray();

        $lockedBadges = Badge::query()
            ->whereNotIn('id', $unlockedBadgeIds)
            ->get();

        $newlyUnlockedBadges = [];

        foreach ($lockedBadges as $badge) {
            $shouldUnlock = false;

            switch ($badge->requirement_type) {
                case 'sessions':
                    $totalSessions = $user->scoringSessions()->count();
                    if ($totalSessions >= $badge->requirement_value) {
                        $shouldUnlock = true;
                    }
                    break;

                case 'level':
                    if ($stats->level >= $badge->requirement_value) {
                        $shouldUnlock = true;
                    }
                    break;

                case 'streak':
                    if ($stats->longest_streak >= $badge->requirement_value) {
                        $shouldUnlock = true;
                    }
                    break;
            }

            if ($shouldUnlock) {
                UserBadge::query()->create([
                    'user_id' => $user->id,
                    'badge_id' => $badge->id,
                    'unlocked_at' => $now,
                ]);

                Notification::query()->create([
                    'user_id' => $user->id,
                    'title' => 'Lencana Baru Terbuka! 🏆',
                    'body' => "Selamat! Anda mendapatkan lencana \"{$badge->name}\": {$badge->description}.",
                    'type' => 'achievement',
                    'read_at' => null,
                    'sent_at' => $now,
                ]);

                $newlyUnlockedBadges[] = $badge;
            }
        }

        return [
            'xp_gained' => $totalGainedXp,
            'xp_breakdown' => [
                'base' => $baseXp,
                'arrows' => $arrowXp,
                'personal_best' => $pbXp,
            ],
            'level_up' => $levelUp,
            'current_level' => $stats->level,
            'current_xp' => $stats->xp,
            'current_streak' => $stats->current_streak,
            'newly_unlocked_badges' => $newlyUnlockedBadges,
        ];
    }
}
