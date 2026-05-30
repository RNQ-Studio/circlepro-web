<?php

namespace App\Services;

use App\Models\ScoringSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Support\Carbon;

/**
 * User profile (Module 0/2) — ensures a profile row exists, derives the cached
 * age_group, and builds the athletic stats summary shown on the profile.
 */
class ProfileService
{
    public function getOrCreate(User $user): UserProfile
    {
        return UserProfile::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Apply validated changes to the user + profile, recomputing age_group.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): UserProfile
    {
        $userFields = array_filter(
            ['full_name' => $data['full_name'] ?? null, 'username' => $data['username'] ?? null],
            fn ($v) => $v !== null,
        );
        if ($userFields !== []) {
            $user->fill($userFields)->save();
        }

        $profile = $this->getOrCreate($user);

        $profileFields = [
            'avatar_url', 'banner_url', 'bio', 'gender', 'birth_date',
            'province', 'city', 'primary_bow_class', 'home_club_id', 'social_links',
        ];
        $changes = [];
        foreach ($profileFields as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }

        if (array_key_exists('birth_date', $data)) {
            $changes['age_group'] = $data['birth_date'] !== null
                ? self::ageGroupFor(Carbon::parse($data['birth_date']))->value
                : null;
        }

        $profile->fill($changes)->save();

        return $profile->refresh();
    }

    /**
     * Athletic stats summary (completed sessions only).
     *
     * @return array<string, mixed>
     */
    public function stats(User $user): array
    {
        $completed = ScoringSession::query()
            ->where('user_id', $user->id)
            ->where('status', ScoringSessionStatus::Completed->value);

        return [
            'total_sessions' => (clone $completed)->count(),
            'total_arrows' => (int) (clone $completed)->sum('arrows_shot'),
            'total_score' => (int) (clone $completed)->sum('total_score'),
            'personal_bests' => $user->personalBests()->count(),
        ];
    }

    /**
     * Map an age to the ranking age-group bucket.
     */
    public static function ageGroupFor(Carbon $birthDate): AgeGroup
    {
        $age = $birthDate->age;

        return match (true) {
            $age < 6 => AgeGroup::Tk,
            $age < 9 => AgeGroup::Sd123,
            $age < 12 => AgeGroup::Sd456,
            $age < 15 => AgeGroup::Smp,
            $age < 18 => AgeGroup::Sma,
            default => AgeGroup::Dewasa,
        };
    }
}
