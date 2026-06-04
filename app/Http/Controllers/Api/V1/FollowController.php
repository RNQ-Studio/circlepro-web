<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\Follow;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    /**
     * Follow a user.
     */
    public function follow(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        if ($follower->id === $user->id) {
            return ApiResponse::error('Anda tidak dapat mengikuti diri sendiri.', 400);
        }

        $follow = Follow::query()->firstOrCreate([
            'follower_id' => $follower->id,
            'followee_id' => $user->id,
        ]);

        if ($follow->wasRecentlyCreated) {
            return ApiResponse::success(null, 'Berhasil mengikuti pengguna.');
        }

        return ApiResponse::success(null, 'Anda sudah mengikuti pengguna ini.');
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(Request $request, User $user): JsonResponse
    {
        $follower = $request->user();

        $deleted = Follow::query()
            ->where('follower_id', $follower->id)
            ->where('followee_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            return ApiResponse::success(null, 'Berhasil batal mengikuti pengguna.');
        }

        return ApiResponse::success(null, 'Anda tidak mengikuti pengguna ini.');
    }

    /**
     * Get a list of users following the target user.
     */
    public function followers(User $user): JsonResponse
    {
        $followers = $user->followers()->with('profile')->get();

        return ApiResponse::success(ProfileResource::collection($followers));
    }

    /**
     * Get a list of users whom the target user is following.
     */
    public function following(User $user): JsonResponse
    {
        $following = $user->followings()->with('profile')->get();

        return ApiResponse::success(ProfileResource::collection($following));
    }
}
