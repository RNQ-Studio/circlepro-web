<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RatingHistoryResource;
use App\Http\Resources\Api\V1\RatingResource;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\Organization;
use App\Models\Rating;
use App\Models\User;
use App\Services\EventService;
use App\Services\RatingEngine;
use App\Support\ApiResponse;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\Gender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RatingController extends Controller
{
    public function __construct(
        private readonly RatingEngine $ratingEngine,
        private readonly EventService $eventService
    ) {}

    /**
     * Finalize division tournament scores and calculate Glicko-2 ratings (organizer only).
     */
    public function finalizeRatings(Request $request, Event $event, EventDivision $division): JsonResponse
    {
        if ($division->event_id !== $event->id) {
            return ApiResponse::error('Divisi tidak cocok dengan event.', 404);
        }

        if (!$this->eventService->canManage($request->user(), $event)) {
            return ApiResponse::error('Unauthorized to finalize ratings.', 403);
        }

        if ($division->rating_status === 'rated') {
            return ApiResponse::error('Rating untuk divisi ini sudah difinalisasi.', 422);
        }

        $this->ratingEngine->computeDivisionRatings($division);

        return ApiResponse::success(null, 'Rating berhasil dihitung dan difinalisasi.');
    }

    /**
     * Get the leaderboard based on bow_class, gender, age_group, and distance_category.
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bow_class' => ['required', Rule::enum(BowClass::class)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'age_group' => ['required', Rule::enum(AgeGroup::class)],
            'distance_category' => ['required', Rule::enum(DistanceCategory::class)],
            'organization_id' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
        ]);

        $orgId = $validated['organization_id'] ?? null;
        if (!$orgId) {
            $platformOrg = Organization::where('slug', 'manahpro')->first();
            $orgId = $platformOrg?->id;
        }

        if (!$orgId) {
            return ApiResponse::error('Platform organization not found.', 500);
        }

        $query = Rating::query()
            ->where('organization_id', $orgId)
            ->where('bow_class', $validated['bow_class'])
            ->where('gender', $validated['gender'])
            ->where('age_group', $validated['age_group'])
            ->where('distance_category', $validated['distance_category'])
            ->where('status', '!=', 'inactive')
            ->with(['user.profile', 'organization']);

        if (!empty($validated['province'])) {
            $query->whereHas('user.profile', function ($q) use ($validated) {
                $q->where('province', $validated['province']);
            });
        }

        if (!empty($validated['city'])) {
            $query->whereHas('user.profile', function ($q) use ($validated) {
                $q->where('city', $validated['city']);
            });
        }

        // Sort by display rating desc
        $query->orderByDesc('display_rating');

        $ratings = $query->paginate($request->integer('limit', 20));

        return ApiResponse::success(RatingResource::collection($ratings));
    }

    /**
     * Get all ratings for a specific user (public).
     */
    public function getUserRatings(User $user): JsonResponse
    {
        $ratings = Rating::where('user_id', $user->id)
            ->with('organization')
            ->orderByDesc('display_rating')
            ->get();

        return ApiResponse::success(RatingResource::collection($ratings));
    }

    /**
     * Get rating history log for a specific rating profile of a user (public).
     */
    public function getRatingHistory(User $user, Rating $rating): JsonResponse
    {
        if ($rating->user_id !== $user->id) {
            return ApiResponse::error('Rating profile tidak cocok dengan user.', 404);
        }

        $history = $rating->histories()
            ->with(['eventDivision.event', 'ratingPeriod'])
            ->orderByDesc('computed_at')
            ->get();

        return ApiResponse::success(RatingHistoryResource::collection($history));
    }

    /**
     * Get ratings for the authenticated user.
     */
    public function getMyRatings(Request $request): JsonResponse
    {
        $ratings = Rating::where('user_id', $request->user()->id)
            ->with('organization')
            ->orderByDesc('display_rating')
            ->get();

        return ApiResponse::success(RatingResource::collection($ratings));
    }
}
