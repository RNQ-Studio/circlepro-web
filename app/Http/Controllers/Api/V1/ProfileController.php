<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateUserProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ManahPro rich user profile (Module 0/2, task 2.2): identity + athletic
 * profile + stats. Distinct from auth `/me`.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    /** Own profile. */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->present($request->user()));
    }

    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        $this->profiles->update($request->user(), $request->validated());

        return ApiResponse::success($this->present($request->user()->refresh()), 'Profile updated');
    }

    /** Public profile of another user. */
    public function showPublic(User $user): JsonResponse
    {
        return ApiResponse::success($this->present($user));
    }

    private function present(User $user): ProfileResource
    {
        $this->profiles->getOrCreate($user);

        $resource = new ProfileResource($user->load('profile'));
        $resource->stats = $this->profiles->stats($user);

        return $resource;
    }
}
