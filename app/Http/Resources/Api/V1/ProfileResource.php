<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Merged identity + athletic profile (Module 0/2). Wraps a [User] with its
 * `profile` relation loaded; [stats] is injected by the controller.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /** @var array<string, mixed> */
    public array $stats = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->profile;
        $auth = $request->user('api') ?? $request->user();
        $isFollowing = false;
        if ($auth && $auth->id !== $this->id) {
            $isFollowing = Follow::where('follower_id', $auth->id)
                ->where('followee_id', $this->id)
                ->exists();
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'full_name' => $this->full_name ?? $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $p?->avatar_url,
            'banner_url' => $p?->banner_url,
            'bio' => $p?->bio,
            'gender' => $p?->gender?->value,
            'birth_date' => $p?->birth_date?->toDateString(),
            'age_group' => $p?->age_group?->value,
            'province' => $p?->province,
            'city' => $p?->city,
            'primary_bow_class' => $p?->primary_bow_class?->value,
            'home_club_id' => $p?->home_club_id,
            'social_links' => $p?->social_links,
            'peak_title' => $p?->peak_title,
            'rating' => null, // national ranking lands in Phase 3
            'stats' => $this->stats,
            'is_following' => $isFollowing,
            'followers_count' => Follow::where('followee_id', $this->id)->count(),
            'following_count' => Follow::where('follower_id', $this->id)->count(),
        ];
    }
}
